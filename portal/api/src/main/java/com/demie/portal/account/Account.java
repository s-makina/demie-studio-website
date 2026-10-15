package com.demie.portal.account;

import java.time.Instant;

/**
 * One row from the {@code account} table. Role is {@code admin} (photographer)
 * or {@code client} (invited); status is {@code pending}, {@code active} or
 * {@code disabled}. Invite fields are only set while a client invite is open.
 */
public record Account(
        long id,
        String email,
        String name,
        String passwordHash,
        String role,
        String status,
        String inviteToken,
        Instant inviteExpiresAt,
        Instant createdAt,
        Instant updatedAt
) {

    public boolean isAdmin() {
        return "admin".equals(role);
    }

    public boolean isActive() {
        return "active".equals(status);
    }
}
