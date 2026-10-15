package com.demie.portal.share;

import com.demie.portal.project.ProjectService;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.security.MessageDigest;
import java.security.SecureRandom;
import java.time.Instant;
import java.util.Base64;
import java.util.List;
import java.util.Optional;

/**
 * One 256-bit share token per project. The raw token is returned to the
 * caller <em>once</em> (issue/rotate); only its SHA-256 hash is persisted.
 * Lookup hashes the presented token and compares with
 * {@link MessageDigest#isEqual} (constant-time) so probes learn nothing
 * from timing.
 */
@Service
public class ShareTokenService {

    private static final SecureRandom RANDOM = new SecureRandom();

    private final JdbcTemplate jdbc;
    private final ProjectService projects;

    public ShareTokenService(JdbcTemplate jdbc, ProjectService projects) {
        this.jdbc = jdbc;
        this.projects = projects;
    }

    // ------------------------------------------------------------ issue/rotate

    /**
     * Issues the project's token. Fails with {@link AlreadyIssuedException}
     * if an active token already exists (rotate instead).
     */
    @Transactional
    public IssuedToken issue(long projectId) {
        projects.findById(projectId).orElseThrow(ProjectService.NotFoundException::new);

        List<TokenRow> existing = rowsByProject(projectId);
        if (!existing.isEmpty() && existing.get(0).revokedAt() == null) {
            throw new AlreadyIssuedException();
        }

        String rawToken = newToken();
        byte[] hash = sha256(rawToken);
        Long id = jdbc.queryForObject(
                "INSERT INTO share_token (project_id, token_hash) VALUES (?, ?) " +
                        "ON CONFLICT (project_id) DO UPDATE " +
                        "SET token_hash = EXCLUDED.token_hash, rotated_at = now(), revoked_at = NULL " +
                        "RETURNING id",
                Long.class, projectId, hash);
        return new IssuedToken(id, projectId, rawToken, Instant.now());
    }

    /**
     * Rotates the project's token: the old raw token stops working
     * immediately, the returned one takes effect. Also clears revocation.
     */
    @Transactional
    public IssuedToken rotate(long projectId) {
        rowsByProject(projectId).stream().findFirst()
                .orElseThrow(ProjectService.NotFoundException::new);

        String rawToken = newToken();
        jdbc.update("UPDATE share_token SET token_hash = ?, rotated_at = now(), " +
                        "revoked_at = NULL WHERE project_id = ?",
                sha256(rawToken), projectId);
        long id = rowsByProject(projectId).get(0).id();
        return new IssuedToken(id, projectId, rawToken, Instant.now());
    }

    /** Revokes the project's token. Revoked tokens are denied instantly. Idempotent. */
    @Transactional
    public void revoke(long projectId) {
        rowsByProject(projectId).stream().findFirst()
                .orElseThrow(ProjectService.NotFoundException::new);
        jdbc.update("UPDATE share_token SET revoked_at = now() WHERE project_id = ? AND revoked_at IS NULL",
                projectId);
    }

    // --------------------------------------------------------------- resolve

    /** Share-link state for the photographer panel (Task 14): no raw token. */
    public ShareStatus status(long projectId) {
        projects.findById(projectId).orElseThrow(ProjectService.NotFoundException::new);
        List<TokenRow> rows = rowsByProject(projectId);
        if (rows.isEmpty()) {
            return new ShareStatus(false, false);
        }
        return new ShareStatus(true, rows.get(0).revokedAt() != null);
    }

    /** Resolves a presented raw token to its grant, or empty when unknown/revoked. */
    public Optional<TokenGrant> resolve(String rawToken) {
        if (rawToken == null || rawToken.isBlank()) {
            return Optional.empty();
        }
        byte[] candidate = sha256(rawToken);
        List<TokenRow> rows = jdbc.query(
                "SELECT id, project_id, token_hash, revoked_at FROM share_token WHERE token_hash = ?",
                (rs, n) -> new TokenRow(rs.getLong("id"), rs.getLong("project_id"),
                        rs.getBytes("token_hash"),
                        rs.getTimestamp("revoked_at") == null ? null
                                : rs.getTimestamp("revoked_at").toInstant()),
                candidate);

        return rows.stream()
                .filter(row -> row.revokedAt() == null
                        && MessageDigest.isEqual(row.tokenHash(), candidate))
                .findFirst()
                .map(row -> new TokenGrant(row.id(), row.projectId()));
    }

    // ---------------------------------------------------------------- helpers

    private List<TokenRow> rowsByProject(long projectId) {
        return jdbc.query(
                "SELECT id, project_id, token_hash, revoked_at FROM share_token WHERE project_id = ?",
                (rs, n) -> new TokenRow(rs.getLong("id"), rs.getLong("project_id"),
                        rs.getBytes("token_hash"),
                        rs.getTimestamp("revoked_at") == null ? null
                                : rs.getTimestamp("revoked_at").toInstant()),
                projectId);
    }

    private static String newToken() {
        byte[] bytes = new byte[32]; // 256-bit
        RANDOM.nextBytes(bytes);
        return Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
    }

    static byte[] sha256(String value) {
        try {
            return MessageDigest.getInstance("SHA-256")
                    .digest(value.getBytes(java.nio.charset.StandardCharsets.UTF_8));
        } catch (java.security.NoSuchAlgorithmException e) {
            throw new IllegalStateException("SHA-256 unavailable", e);
        }
    }

    private record TokenRow(long id, long projectId, byte[] tokenHash, Instant revokedAt) {
    }

    /** Raw token issued once; never persisted. */
    public record IssuedToken(long tokenId, long projectId, String rawToken, Instant issuedAt) {
    }

    /** A validated token presentation. */
    public record TokenGrant(long tokenId, long projectId) {
    }

    /** Whether a project has a share link and whether it is revoked. */
    public record ShareStatus(boolean issued, boolean revoked) {
    }

    /** Thrown when issuing while an active token already exists. */
    public static class AlreadyIssuedException extends RuntimeException {
        public AlreadyIssuedException() {
            super("project already has an active share token");
        }
    }
}
