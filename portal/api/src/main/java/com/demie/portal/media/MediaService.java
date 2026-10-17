package com.demie.portal.media;

import com.demie.portal.account.Account;
import com.demie.portal.project.ProjectService;
import com.demie.portal.s3.S3Service;
import com.demie.portal.share.ShareTokenService;
import org.springframework.dao.DuplicateKeyException;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.PreparedStatementCallback;
import org.springframework.jdbc.core.RowMapper;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import software.amazon.awssdk.services.s3.model.CompletedPart;
import software.amazon.awssdk.services.s3.model.NoSuchUploadException;

import java.net.URLConnection;
import java.nio.charset.StandardCharsets;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.time.Duration;
import java.time.Instant;
import java.util.ArrayList;
import java.util.Base64;
import java.util.List;
import java.util.Optional;

/**
 * Upload lifecycle (Task 6): the API only signs — browsers PUT part bytes
 * straight to S3. Init reserves the {@code media} row and opens the
 * multipart upload; complete closes it and records the verified size.
 * Both are idempotent on the browser-supplied {@code clientUploadId}
 * (V4__media_upload_idempotency.sql): retries, lost responses and replays
 * inside a large batch converge to one row and one multipart upload per
 * file instead of duplicating media.
 * The reaper ({@link MediaUploadReaper}) bounds uploads whose browser
 * never came back. Listing is keyset-paginated on {@code (sort, id)} over
 * the {@code media_project_sort_idx} index (no full scans).
 */
@Service
public class MediaService {

    private static final String COLUMNS =
            "id, project_id, s3_key, size_bytes, kind, sort, status, s3_upload_id, " +
                    "derivative_status, derivative_attempts, thumb_width, thumb_height, created_at";

    private static final int DEFAULT_LIMIT = 50;
    private static final int MAX_LIMIT = 200;
    private static final int MAX_PART_NUMBER = 10_000;
    private static final Duration PART_URL_TTL = Duration.ofMinutes(10);
    private static final Duration VIEW_URL_TTL = Duration.ofMinutes(15);

    private final JdbcTemplate jdbc;
    private final ProjectService projects;
    private final S3Service s3;
    private final ShareTokenService tokens;

    public MediaService(JdbcTemplate jdbc, ProjectService projects, S3Service s3,
                        ShareTokenService tokens) {
        this.jdbc = jdbc;
        this.projects = projects;
        this.s3 = s3;
        this.tokens = tokens;
    }

    // ------------------------------------------------------------------- init

    /**
     * Reserves a media row and opens the S3 multipart upload. Idempotent per
     * {@code clientUploadId}: the browser generates it once per queued file
     * and re-sends it on every retry, so lost responses inside a large batch
     * converge to the same row and upload instead of duplicating media.
     *
     * <ul>
     *   <li>row open ({@code uploading} + live upload id) → returned as-is;
     *       the browser resumes the same upload and re-PUTs remaining parts
     *       (same part number overwrites, already-uploaded parts persist),</li>
     *   <li>row finalized → returned with {@code alreadyUploaded=true} and a
     *       null upload id; the caller's complete call short-circuits.</li>
     * </ul>
     */
    @Transactional
    public MediaInit initUpload(Account caller, long projectId, String filename,
                                String contentType, long sizeBytes, String clientUploadId) {
        projects.getForAccount(projectId, caller);
        String idempotencyKey = requireClientUploadId(clientUploadId);

        MediaInit existing = findExistingByIdempotencyKey(projectId, idempotencyKey);
        if (existing != null) {
            return existing;
        }

        String kind = kindFor(contentType);
        String safeName = sanitizeFilename(filename);
        String objectContentType = (contentType == null || contentType.isBlank())
                ? guessContentType(safeName) : contentType;

        // `sort` is provisional here (batch in-flight ordering) and canonically
        // re-assigned at complete. Two concurrent inits can race the subquery;
        // the per-project unique index arbitrates — see the catch below.
        try {
            Long mediaId = jdbc.queryForObject(
                    "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, client_upload_id) " +
                            "VALUES (?, '', ?, ?, (SELECT COALESCE(MAX(sort), 0) + 1 FROM media WHERE project_id = ?), " +
                            "'uploading', ?) RETURNING id",
                    Long.class, projectId, Math.max(0, sizeBytes), kind, projectId, idempotencyKey);

            String key = "originals/" + projectId + "/" + mediaId + "/" + safeName;
            String uploadId = s3.createMultipartUpload(
                    s3.originalsBucket(), key, objectContentType);
            jdbc.update("UPDATE media SET s3_key = ?, s3_upload_id = ? WHERE id = ?",
                    key, uploadId, mediaId);

            return new MediaInit(mediaId, key, uploadId, false);
        } catch (DuplicateKeyException e) {
            // A twin init with the same key won the insert race — serve its
            // state instead of a second row.
            existing = findExistingByIdempotencyKey(projectId, idempotencyKey);
            if (existing != null) {
                return existing;
            }
            throw e;
        }
    }

    /**
     * State of a prior init with this key, or null when none existed.
     * An {@code uploading} row with no upload id can only come from a crash
     * between the INSERT and the multipart-open — the upload never started,
     * so the stranded row is deleted and a fresh insert may proceed. Two
     * attempts cover the delete-vs-takeover race with a concurrent caller.
     */
    private MediaInit findExistingByIdempotencyKey(long projectId, String idempotencyKey) {
        for (int attempt = 0; attempt < 2; attempt++) {
            List<Media> rows = jdbc.query(
                    "SELECT " + COLUMNS + " FROM media WHERE project_id = ? AND client_upload_id = ?",
                    MAPPER, projectId, idempotencyKey);
            if (rows.isEmpty()) {
                return null;
            }
            Media media = rows.get(0);
            if (media.isUploading()) {
                if (media.uploadId() != null) {
                    return new MediaInit(media.id(), media.s3Key(), media.uploadId(), false);
                }
                int deleted = jdbc.update("DELETE FROM media WHERE id = ? " +
                        "AND status = 'uploading' AND s3_upload_id IS NULL", media.id());
                if (deleted > 0) {
                    return null; // cleaned; caller inserts a fresh row
                }
                continue; // a concurrent call re-opened it; read once more
            }
            return new MediaInit(media.id(), media.s3Key(), null, true);
        }
        return null;
    }

    /** Non-blank, length-capped key the browser assigns once per queued file. */
    private static String requireClientUploadId(String clientUploadId) {
        if (clientUploadId == null || clientUploadId.isBlank()) {
            throw new IllegalArgumentException("clientUploadId is required for idempotent uploads");
        }
        String key = clientUploadId.strip();
        return key.length() > 200 ? key.substring(0, 200) : key;
    }

    /** Presigned URL for one part of an open upload. */
    public S3Service.PresignedPart partUrl(Account caller, long mediaId, int partNumber) {
        if (partNumber < 1 || partNumber > MAX_PART_NUMBER) {
            throw new IllegalArgumentException("partNumber must be 1.." + MAX_PART_NUMBER);
        }
        Media media = findById(mediaId).orElseThrow(ProjectService.NotFoundException::new);
        projects.getForAccount(media.projectId(), caller);
        if (!media.isUploading() || media.uploadId() == null) {
            throw new IllegalArgumentException("upload is not open");
        }
        return s3.presignUploadPart(s3.originalsBucket(), media.s3Key(), media.uploadId(),
                partNumber, PART_URL_TTL);
    }

    // --------------------------------------------------------------- complete

    /**
     * Closes the multipart upload from the browser-supplied part ETags,
     * verifies the object exists, and flips the row to {@code uploaded}
     * (which also enqueues it for derivatives). Idempotent: a retry after a
     * lost response — or an already-finalized row of any kind — returns the
     * finished item instead of failing, and a {@code NoSuchUploadException}
     * from S3 is treated as the crash window between the S3 complete and
     * the DB write (the object verifies, the row finalizes).
     */
    @Transactional
    public MediaItem completeUpload(Account caller, long mediaId, List<PartETag> parts) {
        if (parts == null || parts.isEmpty()) {
            throw new IllegalArgumentException("at least one part ETag is required");
        }
        Media media = findById(mediaId).orElseThrow(ProjectService.NotFoundException::new);
        projects.getForAccount(media.projectId(), caller);

        List<CompletedPart> completed = new ArrayList<>();
        for (PartETag part : parts) {
            if (part.partNumber() < 1 || part.partNumber() > MAX_PART_NUMBER
                    || part.etag() == null || part.etag().isBlank()) {
                throw new IllegalArgumentException("invalid part entry");
            }
            // Browsers/Fetch expose ETags quoted; S3 wants the bare hex.
            String etag = part.etag().replace("\"", "").strip();
            completed.add(CompletedPart.builder().partNumber(part.partNumber()).eTag(etag).build());
        }

        if (!media.isUploading()) {
            // Already finalized (retry after a lost response, twin call): the
            // item is done — replay it as success.
            return itemOf(media);
        }
        if (media.uploadId() == null) {
            throw new IllegalArgumentException("upload is not open");
        }

        try {
            s3.completeMultipartUpload(s3.originalsBucket(), media.s3Key(), media.uploadId(), completed);
        } catch (NoSuchUploadException e) {
            // MPU already completed: the crash window between the S3 call and
            // the DB write below (or a twin complete). Recover only when the
            // finalized object actually exists.
            if (s3.objectSize(s3.originalsBucket(), media.s3Key()).isEmpty()) {
                throw new IllegalStateException("multipart upload vanished before completion", e);
            }
        }

        long actualSize = s3.objectSize(s3.originalsBucket(), media.s3Key())
                .orElseThrow(() -> new IllegalStateException("completed object not found"));

        // Canonical display order is assigned at completion. An advisory lock
        // serializes allocation per project, so two completes in one batch
        // cannot draw the same slot (the (sort, id) keyset still breaks ties).
        PreparedStatementCallback<Void> orderLock = ps -> {
            ps.setLong(1, media.projectId());
            ps.execute();
            return null;
        };
        jdbc.execute("SELECT pg_advisory_xact_lock(?)", orderLock);

        jdbc.update("UPDATE media SET status = 'uploaded', size_bytes = ?, s3_upload_id = NULL, " +
                        "sort = (SELECT COALESCE(MAX(sort), 0) + 1 FROM media WHERE project_id = ?) " +
                        "WHERE id = ? AND status = 'uploading'",
                actualSize, media.projectId(), mediaId);
        // Whether this call or a concurrent twin won the guarded UPDATE, the
        // row now reads final — return its current state.
        return itemOf(findById(mediaId).orElseThrow(ProjectService.NotFoundException::new));
    }

    /**
     * Aborts the multipart upload and drops the reserved row (idempotent:
     * aborting an already-finalized upload does nothing — removal of
     * finished media goes through {@link #deleteMedia}). Aborting a missing
     * upload reads as not-found, so the browser cannot delete foreign rows
     * by brute force.
     */
    @Transactional
    public void abortUpload(Account caller, long mediaId) {
        Media media = findById(mediaId).orElseThrow(ProjectService.NotFoundException::new);
        projects.getForAccount(media.projectId(), caller);
        if (!media.isUploading()) {
            return; // finalized while the abort was in flight (e.g. lost response)
        }
        if (media.uploadId() != null) {
            s3.abortMultipartUpload(s3.originalsBucket(), media.s3Key(), media.uploadId());
        }
        jdbc.update("DELETE FROM media WHERE id = ?", mediaId);
    }

    /**
     * Reaper support: bounds uploads whose browser never returned (tab
     * closed mid-batch, crashed laptop, abandoned retries). Stale
     * {@code uploading} rows get their multipart upload aborted
     * (best-effort — the S3 call swallows already-completed/missing) and
     * the row dropped; rows whose upload never opened carry no S3 call.
     * Rows that finalized while the sweep read them survive the guarded
     * delete. Returns the number of rows reaped.
     */
    public int reapStaleUploads(Duration maxAge, int batch) {
        List<Media> stale = jdbc.query(
                "SELECT " + COLUMNS + " FROM media WHERE status = 'uploading' " +
                        "AND created_at < ? LIMIT ?",
                MAPPER, Timestamp.from(Instant.now().minus(maxAge)), batch);
        for (Media media : stale) {
            if (media.uploadId() != null) {
                s3.abortMultipartUpload(s3.originalsBucket(), media.s3Key(), media.uploadId());
            }
            jdbc.update("DELETE FROM media WHERE id = ? AND status = 'uploading'", media.id());
        }
        return stale.size();
    }

    /**
     * Photographer removes a finished photo (Task 21). Deletes the original
     * plus both derivatives from S3, then the row ({@code download_event}
     * rows survive via {@code ON DELETE SET NULL}). Open uploads are
     * rejected — abort covers that case.
     */
    @Transactional
    public void deleteMedia(Account caller, long mediaId) {
        Media media = findById(mediaId).orElseThrow(ProjectService.NotFoundException::new);
        projects.getForAccount(media.projectId(), caller);
        if (media.isUploading() || media.uploadId() != null) {
            throw new IllegalArgumentException("upload still open — abort it instead");
        }
        s3.deleteObject(s3.thumbsBucket(),
                com.demie.portal.derivative.DerivativeWorker.thumbKeyFor(media));
        s3.deleteObject(s3.previewsBucket(),
                com.demie.portal.derivative.DerivativeWorker.previewKeyFor(media));
        s3.deleteObject(s3.originalsBucket(), media.s3Key());
        jdbc.update("DELETE FROM media WHERE id = ?", mediaId);
    }

    // ---------------------------------------------------------------- listing

    /**
     * Keyset page over {@code (sort, id)}. The cursor is an opaque
     * {@code base64url(sort:id)}; {@code nextCursor} is null on the last page.
     * Items carry presigned thumb/preview URLs (Task 8/9); the internal S3
     * key is never exposed to browsers.
     */
    public MediaPage list(Account caller, long projectId, String cursor, int limit, Integer offset) {
        projects.getForAccount(projectId, caller);
        return page(projectId, cursor, limit, offset);
    }

    /** Token-URL listing: unknown/revoked tokens read as not-found (no oracle). */
    public MediaPage listByToken(String rawToken, String cursor, int limit, Integer offset) {
        ShareTokenService.TokenGrant grant = tokens.resolve(rawToken)
                .orElseThrow(ProjectService.NotFoundException::new);
        return page(grant.projectId(), cursor, limit, offset);
    }

    /**
     * @param offset when non-null and positive, jumps straight to that position
     *               in {@code (sort, id)} order (a "seek"). Otherwise the keyset
     *               {@code cursor} drives the page. Either way {@code total} is
     *               reported so clients can size a timeline scrubber.
     */
    private MediaPage page(long projectId, String cursor, int limit, Integer offset) {
        int pageSize = limit < 1 ? DEFAULT_LIMIT : Math.min(limit, MAX_LIMIT);
        int total = count(projectId);

        List<Media> rows;
        if (offset != null && offset > 0) {
            rows = jdbc.query(
                    "SELECT " + COLUMNS + " FROM media " +
                            "WHERE project_id = ? ORDER BY sort, id LIMIT ? OFFSET ?",
                    MAPPER, projectId, pageSize + 1, offset);
        } else {
            int[] after = decodeCursor(cursor);
            rows = jdbc.query(
                    "SELECT " + COLUMNS + " FROM media " +
                            "WHERE project_id = ? AND (sort > ? OR (sort = ? AND id > ?)) " +
                            "ORDER BY sort, id LIMIT ?",
                    MAPPER, projectId, after[0], after[0], after[1], pageSize + 1);
        }

        boolean hasNext = rows.size() > pageSize;
        List<Media> page = hasNext ? rows.subList(0, pageSize) : rows;
        // `endCursor` points past the last row of this page even when it is the
        // final page — clients resume from it after new uploads land.
        String endCursor = page.isEmpty() ? null
                : encodeCursor(page.get(page.size() - 1).sort(), page.get(page.size() - 1).id());
        String nextCursor = hasNext ? endCursor : null;
        return new MediaPage(page.stream().map(this::viewOf).toList(), nextCursor, endCursor, total);
    }

    private int count(long projectId) {
        Integer total = jdbc.queryForObject(
                "SELECT COUNT(*) FROM media WHERE project_id = ?", Integer.class, projectId);
        return total == null ? 0 : total;
    }

    /** Fresh single-item view (admin or project member): recovers expired URLs. */
    public MediaView view(Account caller, long mediaId) {
        Media media = findById(mediaId).orElseThrow(ProjectService.NotFoundException::new);
        projects.getForAccount(media.projectId(), caller);
        return viewOf(media);
    }

    /** Token-URL single-item view; media outside the token's project reads as missing. */
    public MediaView viewByToken(String rawToken, long mediaId) {
        ShareTokenService.TokenGrant grant = tokens.resolve(rawToken)
                .orElseThrow(ProjectService.NotFoundException::new);
        Media media = findById(mediaId).orElseThrow(ProjectService.NotFoundException::new);
        if (media.projectId() != grant.projectId()) {
            throw new ProjectService.NotFoundException();
        }
        return viewOf(media);
    }

    /**
     * Browser view of a media row: presigned thumb/preview URLs when
     * derivatives are ready (null while pending/failed), never the raw key.
     */
    private MediaView viewOf(Media media) {
        String thumbUrl = null;
        String previewUrl = null;
        if (media.isDerivativeReady()) {
            thumbUrl = s3.presignGet(s3.thumbsBucket(),
                    com.demie.portal.derivative.DerivativeWorker.thumbKeyFor(media),
                    VIEW_URL_TTL).url;
            previewUrl = s3.presignGet(s3.previewsBucket(),
                    com.demie.portal.derivative.DerivativeWorker.previewKeyFor(media),
                    VIEW_URL_TTL).url;
        }
        return new MediaView(media.id(), media.kind(), media.sort(), media.status(),
                media.derivativeStatus(), media.thumbWidth(), media.thumbHeight(),
                media.createdAt().toString(), thumbUrl, previewUrl);
    }

    // ------------------------------------------------------------------ reads

    public Optional<Media> findById(long mediaId) {
        List<Media> rows = jdbc.query(
                "SELECT " + COLUMNS + " FROM media WHERE id = ?", MAPPER, mediaId);
        return rows.stream().findFirst();
    }

    // ---------------------------------------------------------------- helpers

    static String kindFor(String contentType) {
        if (contentType != null) {
            String lower = contentType.toLowerCase();
            if (lower.startsWith("image/")) {
                return "photo";
            }
            if (lower.startsWith("video/")) {
                return "video";
            }
        }
        throw new IllegalArgumentException("contentType must be image/* or video/*");
    }

    static String sanitizeFilename(String filename) {
        if (filename == null || filename.isBlank()) {
            return "file";
        }
        String safe = filename.replace('\\', '/');
        safe = safe.substring(safe.lastIndexOf('/') + 1).strip();
        if (safe.isBlank() || safe.equals(".") || safe.equals("..")) {
            return "file";
        }
        return safe.length() > 200 ? safe.substring(0, 200) : safe;
    }

    private static String guessContentType(String filename) {
        String guessed = URLConnection.guessContentTypeFromName(filename);
        return guessed != null ? guessed : "application/octet-stream";
    }

    static String encodeCursor(int sort, long id) {
        String raw = sort + ":" + id;
        return Base64.getUrlEncoder().withoutPadding()
                .encodeToString(raw.getBytes(StandardCharsets.UTF_8));
    }

    /** Returns {@code [sort, id]}; blank cursor starts before the first row. */
    static int[] decodeCursor(String cursor) {
        if (cursor == null || cursor.isBlank()) {
            return new int[]{0, 0};
        }
        try {
            String[] parts = new String(Base64.getUrlDecoder().decode(cursor),
                    StandardCharsets.UTF_8).split(":", 2);
            return new int[]{Integer.parseInt(parts[0]), Integer.parseInt(parts[1])};
        } catch (Exception e) {
            throw new IllegalArgumentException("invalid cursor");
        }
    }

    static MediaItem itemOf(Media media) {
        return new MediaItem(media.id(), media.s3Key(), media.sizeBytes(), media.kind(),
                media.sort(), media.status());
    }

    private static final RowMapper<Media> MAPPER = MediaService::mapRow;

    private static Media mapRow(ResultSet rs, int rowNum) throws SQLException {
        return new Media(
                rs.getLong("id"),
                rs.getLong("project_id"),
                rs.getString("s3_key"),
                rs.getLong("size_bytes"),
                rs.getString("kind"),
                rs.getInt("sort"),
                rs.getString("status"),
                rs.getString("s3_upload_id"),
                rs.getString("derivative_status"),
                rs.getInt("derivative_attempts"),
                rs.getObject("thumb_width", Integer.class),
                rs.getObject("thumb_height", Integer.class),
                rs.getTimestamp("created_at").toInstant());
    }

    /**
     * Result of {@link #initUpload}: everything the browser needs to
     * upload. {@code uploadId} is null once the row is finalized — the
     * caller sees {@code alreadyUploaded=true} and skips the part/complete
     * phase entirely.
     */
    public record MediaInit(long mediaId, String key, String uploadId, boolean alreadyUploaded) {
    }

    /** One browser-reported uploaded part. */
    public record PartETag(int partNumber, String etag) {
    }

    /** Complete-upload response for the photographer (unchanged shape). */
    public record MediaItem(long id, String s3Key, long sizeBytes, String kind, int sort,
                            String status) {
    }

    /**
     * Listing view of a media row (browser-safe: URLs, no raw keys).
     * {@code thumbWidth}/{@code thumbHeight} drive client-side masonry layout;
     * {@code createdAt} (ISO-8601) drives the upload-day timeline.
     */
    public record MediaView(long id, String kind, int sort, String status,
                            String derivativeStatus, Integer thumbWidth, Integer thumbHeight,
                            String createdAt, String thumbUrl, String previewUrl) {
    }

    /**
     * One listing page. {@code nextCursor} is null when the page ran out;
     * {@code endCursor} always points past the last row (resume anchor after
     * uploads); {@code total} sizes the timeline scrubber.
     */
    public record MediaPage(List<MediaView> items, String nextCursor, String endCursor,
                            int total) {
    }
}
