package com.demie.portal.account;

import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.RowMapper;
import org.springframework.security.core.GrantedAuthority;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.userdetails.User;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.security.SecureRandom;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.time.Duration;
import java.time.Instant;
import java.util.Base64;
import java.util.HexFormat;
import java.util.List;
import java.util.Optional;

/**
 * Accounts are invite-only: photographers are seeded as {@code admin}, clients
 * are created as {@code pending} via {@link #inviteClient} and activated when
 * they accept the invite with a password. There is no self-registration path.
 */
@Service
public class AccountService implements UserDetailsService {

    private static final Duration INVITE_TTL = Duration.ofDays(7);
    private static final SecureRandom RANDOM = new SecureRandom();

    private final JdbcTemplate jdbc;
    private final PasswordEncoder passwordEncoder;

    public AccountService(JdbcTemplate jdbc, PasswordEncoder passwordEncoder) {
        this.jdbc = jdbc;
        this.passwordEncoder = passwordEncoder;
    }

    // ---------------------------------------------------------------- lookups

    public Optional<Account> findByEmail(String email) {
        List<Account> rows = jdbc.query(
                "SELECT id, email, name, password_hash, role, status, invite_token, " +
                        "invite_expires_at, created_at, updated_at FROM account WHERE email = ?",
                MAPPER, email);
        return rows.stream().findFirst();
    }

    public Optional<Account> findById(long id) {
        List<Account> rows = jdbc.query(
                "SELECT id, email, name, password_hash, role, status, invite_token, " +
                        "invite_expires_at, created_at, updated_at FROM account WHERE id = ?",
                MAPPER, id);
        return rows.stream().findFirst();
    }

    public boolean adminExists() {
        Integer count = jdbc.queryForObject(
                "SELECT count(*) FROM account WHERE role = 'admin'", Integer.class);
        return count != null && count > 0;
    }

    // ------------------------------------------------------------ provisioning

    /** Creates an active admin (photographer) account. */
    @Transactional
    public Account createAdmin(String email, String rawPassword, String name) {
        Long id = jdbc.queryForObject(
                "INSERT INTO account (email, name, password_hash, role, status) " +
                        "VALUES (?, ?, ?, 'admin', 'active') RETURNING id",
                Long.class, email, name, passwordEncoder.encode(rawPassword));
        return findById(id).orElseThrow();
    }

    /**
     * Invite-by-email. If an active account already exists the invite is a
     * no-op (returns it with {@code tokenIssued == false}) so the caller can
     * link the existing account instead of duplicating it. Otherwise a
     * {@code pending} client account is created (or its invite refreshed) and
     * a fresh raw token is returned <em>once</em> for the invite email; only
     * its SHA-256 hash is stored.
     */
    @Transactional
    public Invite inviteClient(String email, String name) {
        Optional<Account> existing = findByEmail(email);

        if (existing.isPresent() && existing.get().isActive()) {
            Account a = existing.get();
            return new Invite(a.id(), a.email(), null, null, false);
        }

        String rawToken = newToken();
        Instant expiresAt = Instant.now().plus(INVITE_TTL);

        long accountId;
        if (existing.isPresent()) {
            accountId = existing.get().id();
            jdbc.update(
                    "UPDATE account SET invite_token = ?, invite_expires_at = ?, updated_at = now() " +
                            "WHERE id = ?",
                    sha256Hex(rawToken), java.sql.Timestamp.from(expiresAt), accountId);
        } else {
            accountId = jdbc.queryForObject(
                    "INSERT INTO account (email, name, password_hash, role, status, invite_token, invite_expires_at) " +
                            "VALUES (?, ?, '', 'client', 'pending', ?, ?) RETURNING id",
                    Long.class, email, name, sha256Hex(rawToken), java.sql.Timestamp.from(expiresAt));
        }

        return new Invite(accountId, email, rawToken, expiresAt, true);
    }

    /** Activates a pending client by consuming its invite token and setting a password. */
    @Transactional
    public void acceptInvite(String rawToken, String rawPassword) {
        List<Account> rows = jdbc.query(
                "SELECT id, email, name, password_hash, role, status, invite_token, " +
                        "invite_expires_at, created_at, updated_at FROM account WHERE invite_token = ?",
                MAPPER, sha256Hex(rawToken));

        Account account = rows.stream().findFirst().orElseThrow(InvalidInviteException::new);

        if (account.inviteExpiresAt() == null || account.inviteExpiresAt().isBefore(Instant.now())) {
            throw new InvalidInviteException();
        }

        jdbc.update(
                "UPDATE account SET password_hash = ?, status = 'active', invite_token = NULL, " +
                        "invite_expires_at = NULL, updated_at = now() WHERE id = ?",
                passwordEncoder.encode(rawPassword), account.id());
    }

    // ------------------------------------------------------------- user details

    @Override
    public UserDetails loadUserByUsername(String email) throws UsernameNotFoundException {
        Account account = findByEmail(email)
                .orElseThrow(() -> new UsernameNotFoundException("no account for " + email));

        if (!account.isActive()) {
            throw new UsernameNotFoundException("account not active: " + email);
        }

        List<GrantedAuthority> authorities = account.isAdmin()
                ? List.of(new SimpleGrantedAuthority("ROLE_ADMIN"))
                : List.of(new SimpleGrantedAuthority("ROLE_CLIENT"));

        return User.withUsername(account.email())
                .password(account.passwordHash())
                .authorities(authorities)
                .build();
    }

    // ----------------------------------------------------------------- helpers

    private static final RowMapper<Account> MAPPER = AccountService::mapRow;

    private static Account mapRow(ResultSet rs, int rowNum) throws SQLException {
        java.sql.Timestamp inviteExpires = rs.getTimestamp("invite_expires_at");
        return new Account(
                rs.getLong("id"),
                rs.getString("email"),
                rs.getString("name"),
                rs.getString("password_hash"),
                rs.getString("role"),
                rs.getString("status"),
                rs.getString("invite_token"),
                inviteExpires == null ? null : inviteExpires.toInstant(),
                rs.getTimestamp("created_at").toInstant(),
                rs.getTimestamp("updated_at").toInstant());
    }

    private static String newToken() {
        byte[] bytes = new byte[32];
        RANDOM.nextBytes(bytes);
        return Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
    }

    static String sha256Hex(String value) {
        try {
            MessageDigest digest = MessageDigest.getInstance("SHA-256");
            return HexFormat.of().formatHex(digest.digest(value.getBytes(StandardCharsets.UTF_8)));
        } catch (NoSuchAlgorithmException e) {
            throw new IllegalStateException("SHA-256 unavailable", e);
        }
    }

    /** Result of {@link #inviteClient}: {@code rawToken} is present only when a new invite was issued. */
    public record Invite(long accountId, String email, String rawToken, Instant expiresAt, boolean tokenIssued) {
    }

    /** Thrown when an invite token is unknown or expired. */
    public static class InvalidInviteException extends RuntimeException {
        public InvalidInviteException() {
            super("invalid or expired invite token");
        }
    }
}
