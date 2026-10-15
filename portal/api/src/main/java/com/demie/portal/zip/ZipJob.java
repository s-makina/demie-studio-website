package com.demie.portal.zip;

import java.time.Instant;
import java.util.List;

/**
 * Background ZIP build row (Task 11). {@code mediaIds} is the exact selected
 * subset in build order; empty at rest never happens (an empty request means
 * "all uploaded media", resolved at request time). {@code sizeBytes} and
 * {@code s3Key} are set when the worker finishes; {@code expiresAt} is
 * {@code readyAt + 7 days}.
 */
public record ZipJob(long id, long projectId, String status, List<Long> mediaIds,
                     String s3Key, Long sizeBytes, String errorSummary, Instant createdAt,
                     Instant startedAt, Instant readyAt, Instant expiresAt) {

    public boolean isLive() {
        return switch (status) {
            case "pending", "running", "ready" -> true;
            default -> false;
        };
    }

    public boolean isExpired(Instant now) {
        return "ready".equals(status) && expiresAt != null && !expiresAt.isAfter(now);
    }
}
