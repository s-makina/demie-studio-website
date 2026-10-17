package com.demie.portal.media;

import java.time.Instant;

/**
 * One row from the {@code media} table. Status is {@code uploading} (multipart
 * in flight), {@code uploaded} (bytes in S3) or {@code derivative_failed};
 * {@code uploadId} is set only while a multipart upload is open.
 *
 * <p>{@code thumbWidth}/{@code thumbHeight} are the derivative thumb's pixel
 * size (Task 22), recorded so browsers can lay out a virtualized masonry
 * without measuring images. They are {@code null} until the worker reads them
 * and {@code 0} when the thumb exists but its size could not be read.
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
        Integer thumbWidth,
        Integer thumbHeight,
        Instant createdAt
) {

    public boolean isUploading() {
        return "uploading".equals(status);
    }

    public boolean isDerivativeReady() {
        return "ready".equals(derivativeStatus);
    }
}
