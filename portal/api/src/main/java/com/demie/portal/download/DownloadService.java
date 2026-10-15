package com.demie.portal.download;

import com.demie.portal.account.Account;
import com.demie.portal.media.Media;
import com.demie.portal.media.MediaService;
import com.demie.portal.project.ProjectService;
import com.demie.portal.s3.S3Service;
import com.demie.portal.share.ShareTokenService;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;

import java.time.Duration;

/**
 * Per-file download + analytics events (Task 10). Both access paths resolve
 * to a short-lived presigned GET of the <em>original</em>; every view and
 * download writes a {@code download_event} row so per-project counts
 * increment. Unknown/revoked tokens read as not-found (no oracle).
 */
@Service
public class DownloadService {

    private static final Duration DOWNLOAD_URL_TTL = Duration.ofMinutes(10);

    private final JdbcTemplate jdbc;
    private final MediaService media;
    private final ProjectService projects;
    private final ShareTokenService tokens;
    private final S3Service s3;

    public DownloadService(JdbcTemplate jdbc, MediaService media, ProjectService projects,
                           ShareTokenService tokens, S3Service s3) {
        this.jdbc = jdbc;
        this.media = media;
        this.projects = projects;
        this.tokens = tokens;
        this.s3 = s3;
    }

    /** Logged-in download: caller must see the media's project. */
    public DownloadTarget downloadForAccount(Account caller, long mediaId) {
        Media row = media.findById(mediaId).orElseThrow(ProjectService.NotFoundException::new);
        projects.getForAccount(row.projectId(), caller);
        return download(row, caller.id(), null);
    }

    /** Token-URL download: token grants exactly its project. */
    public DownloadTarget downloadForToken(String rawToken, long mediaId) {
        ShareTokenService.TokenGrant grant = tokens.resolve(rawToken)
                .orElseThrow(ProjectService.NotFoundException::new);
        Media row = media.findById(mediaId).orElseThrow(ProjectService.NotFoundException::new);
        if (row.projectId() != grant.projectId()) {
            throw new ProjectService.NotFoundException();
        }
        return download(row, null, grant.tokenId());
    }

    /** Project view from a logged-in account (grid page load). */
    public void recordAccountView(Account caller, long projectId) {
        projects.getForAccount(projectId, caller);
        insertEvent(projectId, null, caller.id(), null, "view");
    }

    /** Project view from a token URL (no login). */
    public void recordTokenView(String rawToken) {
        ShareTokenService.TokenGrant grant = tokens.resolve(rawToken)
                .orElseThrow(ProjectService.NotFoundException::new);
        insertEvent(grant.projectId(), null, null, grant.tokenId(), "view");
    }

    private DownloadTarget download(Media row, Long accountId, Long tokenId) {
        if (row.isUploading()) {
            throw new IllegalArgumentException("media is still uploading");
        }
        String filename = filenameOf(row);
        String url = s3.presignGet(s3.originalsBucket(), row.s3Key(), DOWNLOAD_URL_TTL,
                filename).url;
        insertEvent(row.projectId(), row.id(), accountId, tokenId, "download");
        return new DownloadTarget(url, filename);
    }

    private void insertEvent(long projectId, Long mediaId, Long accountId, Long tokenId,
                             String eventType) {
        jdbc.update("INSERT INTO download_event " +
                        "(project_id, media_id, account_id, token_id, event_type) " +
                        "VALUES (?, ?, ?, ?, ?)",
                projectId, mediaId, accountId, tokenId, eventType);
    }

    private static String filenameOf(Media row) {
        String key = row.s3Key();
        int slash = key.lastIndexOf('/');
        String name = slash < 0 ? key : key.substring(slash + 1);
        return name.isBlank() ? "download" : name;
    }

    /** Presigned original URL plus the filename the browser should save as. */
    public record DownloadTarget(String url, String filename) {
    }
}
