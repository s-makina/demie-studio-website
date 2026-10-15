package com.demie.portal.project;

import com.demie.portal.account.Account;
import com.demie.portal.account.AccountService;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.jdbc.core.RowMapper;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.sql.ResultSet;
import java.sql.SQLException;
import java.util.List;
import java.util.Optional;

/**
 * Photographer-owned projects with invite-based membership. Mutations are
 * admin-only (enforced in {@code SecurityConfig}); reads are filtered here:
 * admins see everything, clients see only active projects they belong to.
 */
@Service
public class ProjectService {

    private static final String COLUMNS =
            "id, account_id, name, client_name, status, cover_s3_key, created_at, updated_at";

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

    /** Admins see all projects; clients see active projects they belong to. */
    public List<Project> listFor(Account account) {
        if (account.isAdmin()) {
            return jdbc.query("SELECT " + COLUMNS + " FROM project ORDER BY id", MAPPER);
        }
        return jdbc.query(
                "SELECT p." + COLUMNS.replace(", ", ", p.") +
                        " FROM project p JOIN project_member m ON m.project_id = p.id " +
                        "WHERE m.account_id = ? AND p.status = 'active' ORDER BY p.id",
                MAPPER, account.id());
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
}
