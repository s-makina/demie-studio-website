package com.demie.portal.media;

import java.time.Instant;

/**
 * One row from the {@code media} table. Status is {@code uploading} (multipart
 * in flight), {@code uploaded} (bytes in S3) or {@code derivative_failed};
 * {@code uploadId} is set only while a multipart upload is open.
 */
public record Media(
        long id,
        long projectId,
        String s3Key,
        long sizeBytes,
        String kind,
        int sort,
        String status,
        String uploadId,
        String derivativeStatus,
        int derivativeAttempts,
        Instant createdAt
) {

    public boolean isUploading() {
        return "uploading".equals(status);
    }
}
