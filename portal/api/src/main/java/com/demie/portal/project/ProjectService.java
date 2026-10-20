package com.demie.portal.project;

import com.demie.portal.account.Account;
import com.demie.portal.account.AccountService;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.RowMapper;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.nio.charset.StandardCharsets;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.Base64;
import java.util.List;
import java.util.Optional;

/**
 * Photographer-owned projects with invite-based membership. Mutations are
 * admin-only (enforced in {@code SecurityConfig}); reads are filtered here:
 * admins see everything, clients see only active projects they belong to.
 * Listing is keyset-paginated on {@code id} descending (one row per shoot,
 * forever — no full scans), filtered server-side by {@code status}.
 */
@Service
public class ProjectService {

    private static final String COLUMNS =
            "id, account_id, name, client_name, status, cover_s3_key, created_at, updated_at";

    private static final int DEFAULT_LIMIT = 24;
    private static final int MAX_LIMIT = 200;

    private final JdbcTemplate jdbc;
    private final AccountService accounts;

    public ProjectService(JdbcTemplate jdbc, AccountService accounts) {
        this.jdbc = jdbc;
        this.accounts = accounts;
    }

    // ----------------------------------------------------------------- create

    /** Creates a project owned by {@code ownerId} (must be an admin). */
    @Transactional
    public Project create(long ownerId, String name, String clientName) {
        if (name == null || name.isBlank()) {
            throw new IllegalArgumentException("project name is required");
        }
        Long id = jdbc.queryForObject(
                "INSERT INTO project (account_id, name, client_name) VALUES (?, ?, ?) RETURNING id",
                Long.class, ownerId, name.strip(), clientName);
        return findById(id).orElseThrow();
    }

    // ----------------------------------------------------------------- update

    /**
     * Renames and/or re-statuses a project. Null arguments leave the field
     * unchanged. Status must be {@code active} or {@code archived}.
     */
    @Transactional
    public Project update(long projectId, String name, String status) {
        Project current = findById(projectId).orElseThrow(NotFoundException::new);

        String nextName = current.name();
        if (name != null) {
            if (name.isBlank()) {
                throw new IllegalArgumentException("project name must not be blank");
            }
            nextName = name.strip();
        }

        String nextStatus = current.status();
        if (status != null) {
            if (!status.equals("active") && !status.equals("archived")) {
                throw new IllegalArgumentException("status must be active or archived");
            }
            nextStatus = status;
        }

        jdbc.update("UPDATE project SET name = ?, status = ?, updated_at = now() WHERE id = ?",
                nextName, nextStatus, projectId);
        return findById(projectId).orElseThrow();
    }

    // ---------------------------------------------------------------- members

    /**
     * Assigns an existing (invited) account to a project as {@code viewer}.
     * Idempotent: re-assigning keeps a single membership row.
     */
    @Transactional
    public void assignMemberByEmail(long projectId, String email) {
        findById(projectId).orElseThrow(NotFoundException::new);
        Account account = accounts.findByEmail(email).orElseThrow(NotFoundException::new);
        jdbc.update("INSERT INTO project_member (project_id, account_id, role) " +
                        "VALUES (?, ?, 'viewer') ON CONFLICT (project_id, account_id) DO NOTHING",
                projectId, account.id());
    }

    // ------------------------------------------------------------------ reads

    public Optional<Project> findById(long projectId) {
        List<Project> rows = jdbc.query(
                "SELECT " + COLUMNS + " FROM project WHERE id = ?", MAPPER, projectId);
        return rows.stream().findFirst();
    }

    /**
     * Cursor page of projects visible to {@code account}, newest first.
     * {@code statusFilter} is {@code active}, {@code archived}, or {@code all}
     * (null/blank = all). The cursor is an opaque {@code base64url(id)};
     * blank starts at the newest row, malformed throws
     * {@link IllegalArgumentException} (mapped to 400). {@code nextCursor}
     * is null on the last page.
     *
     * <p>Clients keep the exact visibility of the old array path: active
     * memberships only, regardless of the requested filter — a client-listed
     * page is just a short slice of their memberships.
     */
    public ProjectPage listFor(Account account, String statusFilter, String cursor, int limit) {
        String status = statusFilter == null || statusFilter.isBlank() ? "all" : statusFilter;
        if (!status.equals("active") && !status.equals("archived") && !status.equals("all")) {
            throw new IllegalArgumentException("status must be active, archived, or all");
        }
        int pageSize = limit < 1 ? DEFAULT_LIMIT : Math.min(limit, MAX_LIMIT);
        long afterId = decodeCursor(cursor);
        boolean admin = account.isAdmin();

        String statusClause = switch (status) {
            case "active" -> " AND status = 'active'";
            case "archived" -> " AND status = 'archived'";
            case "all" -> "";
            default -> ""; // unreachable — validated above
        };

        List<Project> rows;
        if (admin) {
            rows = jdbc.query(
                    "SELECT " + COLUMNS + " FROM project WHERE id < ?" + statusClause +
                            " ORDER BY id DESC LIMIT ?",
                    MAPPER, afterId, pageSize + 1);
        } else {
            rows = jdbc.query(
                    "SELECT p." + COLUMNS.replace(", ", ", p.") +
                            " FROM project p JOIN project_member m ON m.project_id = p.id " +
                            "WHERE m.account_id = ? AND p.status = 'active' AND p.id < ? " +
                            "ORDER BY p.id DESC LIMIT ?",
                    MAPPER, account.id(), afterId, pageSize + 1);
        }

        boolean hasNext = rows.size() > pageSize;
        List<Project> items = hasNext ? rows.subList(0, pageSize) : rows;
        String nextCursor = hasNext
                ? encodeCursor(items.get(items.size() - 1).id())
                : null;

        long activeCount = countVisible(account, true);
        long archivedCount = admin ? countVisible(account, false) : 0;
        long total = switch (status) {
            case "active" -> activeCount;
            case "archived" -> archivedCount;
            case "all" -> activeCount + archivedCount;
            default -> 0; // unreachable — validated above
        };
        return new ProjectPage(items, nextCursor, total, activeCount, archivedCount);
    }

    /**
     * COUNT over one caller-visible slice of the table, active or archived.
     * Clients only ever see active memberships, so the archived half is
     * reported as 0 without touching the database.
     */
    private long countVisible(Account account, boolean active) {
        if (account.isAdmin()) {
            Long count = jdbc.queryForObject(
                    "SELECT COUNT(*) FROM project WHERE status = ?",
                    Long.class, active ? "active" : "archived");
            return count == null ? 0 : count;
        }
        if (!active) {
            return 0;
        }
        Long count = jdbc.queryForObject(
                "SELECT COUNT(*) FROM project p JOIN project_member m ON m.project_id = p.id " +
                        "WHERE m.account_id = ? AND p.status = 'active'",
                Long.class, account.id());
        return count == null ? 0 : count;
    }

    /** Opaque keyset cursor ({@code base64url(id)}). */
    static String encodeCursor(long id) {
        return Base64.getUrlEncoder().withoutPadding()
                .encodeToString(Long.toString(id).getBytes(StandardCharsets.UTF_8));
    }

    /** Id strictly below which the next page starts; blank starts at the top. */
    static long decodeCursor(String cursor) {
        if (cursor == null || cursor.isBlank()) {
            return Long.MAX_VALUE;
        }
        try {
            return Long.parseLong(new String(Base64.getUrlDecoder().decode(cursor),
                    StandardCharsets.UTF_8));
        } catch (Exception e) {
            throw new IllegalArgumentException("invalid cursor");
        }
    }

    /**
     * Single-project read with access check. Admins may read anything;
     * clients need membership on an active project.
     */
    public Project getForAccount(long projectId, Account account) {
        Project project = findById(projectId).orElseThrow(NotFoundException::new);
        if (account.isAdmin()) {
            return project;
        }
        if (project.isArchived() || !isMember(projectId, account.id())) {
            throw new AccessDeniedException();
        }
        return project;
    }

    private boolean isMember(long projectId, long accountId) {
        Integer count = jdbc.queryForObject(
                "SELECT count(*) FROM project_member WHERE project_id = ? AND account_id = ?",
                Integer.class, projectId, accountId);
        return count != null && count > 0;
    }

    // ----------------------------------------------------------------- mapping

    private static final RowMapper<Project> MAPPER = ProjectService::mapRow;

    private static Project mapRow(ResultSet rs, int rowNum) throws SQLException {
        return new Project(
                rs.getLong("id"),
                rs.getLong("account_id"),
                rs.getString("name"),
                rs.getString("client_name"),
                rs.getString("status"),
                rs.getString("cover_s3_key"),
                rs.getTimestamp("created_at").toInstant(),
                rs.getTimestamp("updated_at").toInstant());
    }

    /** Thrown when the project (or assigned account) does not exist. */
    public static class NotFoundException extends RuntimeException {
        public NotFoundException() {
            super("not found");
        }
    }

    /** Thrown when a non-member client requests a project. */
    public static class AccessDeniedException extends RuntimeException {
        public AccessDeniedException() {
            super("not a project member");
        }
    }

    /**
     * One listing page of raw {@link Project} rows plus the aggregates that
     * sized it: {@code total} matches the requested status filter, and
     * {@code counts} carries the caller-visible active/archived split for the
     * web filter tabs. The controller maps rows to its response shape.
     */
    public record ProjectPage(List<Project> items, String nextCursor, long total,
                              long activeCount, long archivedCount) {
    }
}
