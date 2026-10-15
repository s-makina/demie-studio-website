package com.demie.portal.zip;

import com.demie.portal.account.Account;
import com.demie.portal.project.Project;
import com.demie.portal.project.ProjectService;
import com.demie.portal.s3.S3Service;
import com.demie.portal.share.ShareTokenService;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.RowMapper;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.sql.Array;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.time.Duration;
import java.time.Instant;
import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

/**
 * ZIP request API + job state machine (Task 11). Clients (and token holders)
 * request a ZIP of all uploaded media or an explicit subset; the request is
 * idempotent per selection — an in-flight or still-live job for the same
 * project + selection is returned instead of a duplicate ("resume": close
 * the tab, come back, get the same job). The worker (Task 12) drives
 * {@code pending → running → ready}; reads lazily flip past-expiry
 * {@code ready} jobs to {@code expired}, and a new POST rebuilds.
 */
@Service
public class ZipJobService {

    static final Duration ZIP_URL_TTL = Duration.ofMinutes(10);

    private static final String COLUMNS =
            "id, project_id, status, media_ids, s3_key, size_bytes, error_summary, " +
                    "created_at, started_at, ready_at, expires_at";

    private final JdbcTemplate jdbc;
    private final ProjectService projects;
    private final ShareTokenService tokens;
    private final S3Service s3;

    public ZipJobService(JdbcTemplate jdbc, ProjectService projects,
                         ShareTokenService tokens, S3Service s3) {
        this.jdbc = jdbc;
        this.projects = projects;
        this.tokens = tokens;
        this.s3 = s3;
    }

    // ---------------------------------------------------------------- request

    /** Logged-in ZIP request: caller must see the project (member or admin). */
    @Transactional
    public ZipJob requestForAccount(Account caller, long projectId, List<Long> mediaIds) {
        projects.getForAccount(projectId, caller);
        return request(projectId, mediaIds);
    }

    /** Token-URL ZIP request: token grants exactly its project. */
    @Transactional
    public ZipJob requestForToken(String rawToken, List<Long> mediaIds) {
        ShareTokenService.TokenGrant grant = tokens.resolve(rawToken)
                .orElseThrow(ProjectService.NotFoundException::new);
        return request(grant.projectId(), mediaIds);
    }

    private ZipJob request(long projectId, List<Long> mediaIds) {
        List<Long> selection = resolveSelection(projectId, mediaIds);
        Instant now = Instant.now();
        for (ZipJob live : liveJobs(projectId)) {
            if (!live.isExpired(now) && live.mediaIds().equals(selection)) {
                return live;
            }
        }
        Long id = jdbc.queryForObject(
                "INSERT INTO zip_job (project_id, status, media_ids) VALUES (?, 'pending', ?) " +
                        "RETURNING id",
                Long.class, projectId, selection.stream().map(String::valueOf).toArray(String[]::new));
        return findById(id).orElseThrow();
    }

    /**
     * Null/empty means "all uploaded media in display order"; otherwise every
     * id must be an uploaded row of this project. Unknown/cross-project ids
     * read as not-found (no oracle); still-uploading rows are a bad request.
     */
    private List<Long> resolveSelection(long projectId, List<Long> mediaIds) {
        if (mediaIds == null || mediaIds.isEmpty()) {
            return jdbc.queryForList(
                    "SELECT id FROM media WHERE project_id = ? AND status = 'uploaded' " +
                            "ORDER BY sort, id",
                    Long.class, projectId);
        }
        List<Long> distinct = mediaIds.stream().distinct().toList();
        List<Long> rows = jdbc.query(
                "SELECT id, status FROM media WHERE project_id = ? AND id = ANY (?) " +
                        "ORDER BY sort, id",
                (rs, n) -> {
                    String status = rs.getString("status");
                    if (!"uploaded".equals(status)) {
                        throw new IllegalArgumentException("media is still uploading");
                    }
                    return rs.getLong("id");
                }, projectId, distinct.toArray(Long[]::new));
        if (rows.size() != distinct.size()) {
            throw new ProjectService.NotFoundException();
        }
        return rows;
    }

    private List<ZipJob> liveJobs(long projectId) {
        return jdbc.query("SELECT " + COLUMNS + " FROM zip_job WHERE project_id = ? " +
                        "AND status IN ('pending', 'running', 'ready') ORDER BY id",
                MAPPER, projectId);
    }

    // -------------------------------------------------------------------- poll

    /** Logged-in job poll, scoped to the path project. */
    public ZipJobView getForAccount(Account caller, long projectId, long jobId) {
        ZipJob job = findById(jobId).orElseThrow(ProjectService.NotFoundException::new);
        if (job.projectId() != projectId) {
            throw new ProjectService.NotFoundException();
        }
        projects.getForAccount(projectId, caller);
        return viewOf(expireIfNeeded(job));
    }

    /** Token-URL job poll (no login); job must belong to the token's project. */
    public ZipJobView getForToken(String rawToken, long jobId) {
        ShareTokenService.TokenGrant grant = tokens.resolve(rawToken)
                .orElseThrow(ProjectService.NotFoundException::new);
        ZipJob job = findById(jobId).orElseThrow(ProjectService.NotFoundException::new);
        if (job.projectId() != grant.projectId()) {
            throw new ProjectService.NotFoundException();
        }
        return viewOf(expireIfNeeded(job));
    }

    /** Past-expiry {@code ready} flips to {@code expired} on read. */
    private ZipJob expireIfNeeded(ZipJob job) {
        if (!job.isExpired(Instant.now())) {
            return job;
        }
        int updated = jdbc.update(
                "UPDATE zip_job SET status = 'expired' WHERE id = ? AND status = 'ready'",
                job.id());
        return updated == 1 ? findById(job.id()).orElseThrow() : job;
    }

    // --------------------------------------------------------------- download

    /** Logged-in ZIP download: 302 target + analytics event. */
    public ZipDownload downloadForAccount(Account caller, long projectId, long jobId) {
        ZipJob job = findById(jobId).orElseThrow(ProjectService.NotFoundException::new);
        if (job.projectId() != projectId) {
            throw new ProjectService.NotFoundException();
        }
        projects.getForAccount(projectId, caller);
        return download(job, caller.id(), null);
    }

    /** Token-URL ZIP download (no login). */
    public ZipDownload downloadForToken(String rawToken, long jobId) {
        ShareTokenService.TokenGrant grant = tokens.resolve(rawToken)
                .orElseThrow(ProjectService.NotFoundException::new);
        ZipJob job = findById(jobId).orElseThrow(ProjectService.NotFoundException::new);
        if (job.projectId() != grant.projectId()) {
            throw new ProjectService.NotFoundException();
        }
        return download(job, null, grant.tokenId());
    }

    private ZipDownload download(ZipJob job, Long accountId, Long tokenId) {
        ZipJob current = expireIfNeeded(job);
        if (!"ready".equals(current.status()) || current.s3Key() == null) {
            if ("pending".equals(current.status()) || "running".equals(current.status())) {
                throw new NotReadyException();
            }
            throw new ProjectService.NotFoundException();
        }
        String filename = zipFilename(current.projectId());
        String url = s3.presignGet(s3.zipsBucket(), current.s3Key(), ZIP_URL_TTL, filename).url;
        jdbc.update("INSERT INTO download_event " +
                        "(project_id, media_id, account_id, token_id, event_type) " +
                        "VALUES (?, NULL, ?, ?, 'download')",
                current.projectId(), accountId, tokenId);
        return new ZipDownload(url, filename);
    }

    private String zipFilename(long projectId) {
        String base = projects.findById(projectId).map(Project::name).orElse("photos");
        String safe = base.strip().replaceAll("[^A-Za-z0-9 _-]+", "").strip()
                .replaceAll("\\s+", "-");
        if (safe.isBlank()) {
            safe = "photos";
        }
        if (safe.length() > 80) {
            safe = safe.substring(0, 80);
        }
        return safe + ".zip";
    }

    // ------------------------------------------------------------------ reads

    public Optional<ZipJob> findById(long jobId) {
        List<ZipJob> rows = jdbc.query(
                "SELECT " + COLUMNS + " FROM zip_job WHERE id = ?", MAPPER, jobId);
        return rows.stream().findFirst();
    }

    static ZipJobView viewOf(ZipJob job) {
        return new ZipJobView(job.id(), job.projectId(), job.status(), job.mediaIds().size(),
                job.sizeBytes(), job.errorSummary(),
                job.expiresAt() == null ? null : job.expiresAt().toString());
    }

    private static final RowMapper<ZipJob> MAPPER = ZipJobService::mapRow;

    private static ZipJob mapRow(ResultSet rs, int rowNum) throws SQLException {
        List<Long> mediaIds = new ArrayList<>();
        Array array = rs.getArray("media_ids");
        if (array != null) {
            for (Object value : (Object[]) array.getArray()) {
                mediaIds.add(Long.parseLong(String.valueOf(value)));
            }
        }
        Long sizeBytes = rs.getLong("size_bytes");
        if (rs.wasNull()) {
            sizeBytes = null;
        }
        return new ZipJob(
                rs.getLong("id"),
                rs.getLong("project_id"),
                rs.getString("status"),
                List.copyOf(mediaIds),
                rs.getString("s3_key"),
                sizeBytes,
                rs.getString("error_summary"),
                rs.getTimestamp("created_at").toInstant(),
                rs.getTimestamp("started_at") == null ? null
                        : rs.getTimestamp("started_at").toInstant(),
                rs.getTimestamp("ready_at") == null ? null
                        : rs.getTimestamp("ready_at").toInstant(),
                rs.getTimestamp("expires_at") == null ? null
                        : rs.getTimestamp("expires_at").toInstant());
    }

    /** Poll view: counts and readiness without internal S3 keys. */
    public record ZipJobView(long id, long projectId, String status, int mediaCount,
                             Long sizeBytes, String error, String expiresAt) {
    }

    /** Presigned ZIP URL plus the filename the browser should save as. */
    public record ZipDownload(String url, String filename) {
    }

    /** Thrown when the ZIP is requested before the build finishes. */
    public static class NotReadyException extends RuntimeException {
        public NotReadyException() {
            super("zip is not ready yet");
        }
    }
}
