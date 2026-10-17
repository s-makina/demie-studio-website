package com.demie.portal.media;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.autoconfigure.condition.ConditionalOnProperty;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

import java.time.Duration;

/**
 * Periodic cleanup of uploads whose browser never returned (tab closed
 * mid-batch, crashed laptop, abandoned retries). Stale {@code uploading}
 * rows (see {@link MediaService#reapStaleUploads}) get their multipart
 * upload aborted and the row dropped, bounding stranded S3 storage and
 * stuck rows no matter how the client died. Disabled in tests via
 * {@code media.reaper.enabled=false} so background sweeps never race the
 * upload fixtures.
 */
@Component
@ConditionalOnProperty(name = "media.reaper.enabled", havingValue = "true", matchIfMissing = true)
public class MediaUploadReaper {

    private final MediaService media;
    private final Duration maxAge;
    private final int batchSize;

    public MediaUploadReaper(MediaService media,
                             @Value("${MEDIA_UPLOAD_MAX_AGE:24h}") Duration maxAge,
                             @Value("${MEDIA_REAPER_BATCH:100}") int batchSize) {
        this.media = media;
        this.maxAge = maxAge;
        this.batchSize = batchSize;
    }

    @Scheduled(fixedDelayString = "${MEDIA_REAPER_POLL_MILLIS:3600000}")
    public void sweep() {
        media.reapStaleUploads(maxAge, batchSize);
    }
}
