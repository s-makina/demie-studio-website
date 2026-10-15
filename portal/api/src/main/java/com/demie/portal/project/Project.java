package com.demie.portal.project;

import java.time.Instant;

/**
 * One row from the {@code project} table. Status is {@code active} or
 * {@code archived}; archived projects are hidden from client lists.
 */
public record Project(
        long id,
        long accountId,
        String name,
        String clientName,
        String status,
        String coverS3Key,
        Instant createdAt,
        Instant updatedAt
) {

    public boolean isArchived() {
        return "archived".equals(status);
    }
}
