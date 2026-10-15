package com.demie.portal.project;

import com.demie.portal.account.Account;
import com.demie.portal.account.AccountService;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PatchMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;

import java.time.Instant;
import java.util.List;
import java.util.Map;

/**
 * Project endpoints. Mutations are admin-only (see {@code SecurityConfig});
 * reads are scoped per caller by {@link ProjectService}.
 */
@RestController
@RequestMapping("/projects")
public class ProjectController {

    private final ProjectService projects;
    private final AccountService accounts;
    private final com.demie.portal.download.DownloadService downloads;

    public ProjectController(ProjectService projects, AccountService accounts,
                             com.demie.portal.download.DownloadService downloads) {
        this.projects = projects;
        this.accounts = accounts;
        this.downloads = downloads;
    }

    /** Photographer creates a project. */
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public ProjectResponse create(@Valid @RequestBody CreateRequest request,
                                  Authentication authentication) {
        Account owner = currentAccount(authentication);
        Project project = projects.create(owner.id(), request.name(), request.clientName());
        return ProjectResponse.from(project);
    }

    /** Scoped project list: all for admins, member projects for clients. */
    @GetMapping
    public List<ProjectResponse> list(Authentication authentication) {
        Account caller = currentAccount(authentication);
        return projects.listFor(caller).stream().map(ProjectResponse::from).toList();
    }

    /** Single project with membership check for clients (records a view event). */
    @GetMapping("/{id}")
    public ProjectResponse get(@PathVariable long id, Authentication authentication) {
        Account caller = currentAccount(authentication);
        ProjectResponse response = ProjectResponse.from(projects.getForAccount(id, caller));
        downloads.recordAccountView(caller, id);
        return response;
    }

    /** Photographer renames and/or archives a project. */
    @PatchMapping("/{id}")
    public ProjectResponse update(@PathVariable long id,
                                  @RequestBody UpdateRequest request,
                                  Authentication authentication) {
        currentAccount(authentication);
        return ProjectResponse.from(projects.update(id, request.name(), request.status()));
    }

    /** Photographer assigns an invited email to a project. */
    @PostMapping("/{id}/members")
    public Map<String, Object> assignMember(@PathVariable long id,
                                            @Valid @RequestBody AssignRequest request,
                                            Authentication authentication) {
        currentAccount(authentication);
        projects.assignMemberByEmail(id, request.email());
        return Map.of("projectId", id, "email", request.email());
    }

    private Account currentAccount(Authentication authentication) {
        return accounts.findByEmail(authentication.getName()).orElseThrow(
                () -> new ProjectService.NotFoundException());
    }

    // ------------------------------------------------------------- error mapping

    @ExceptionHandler(ProjectService.NotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public Map<String, String> notFound(ProjectService.NotFoundException e) {
        return Map.of("error", "not_found");
    }

    @ExceptionHandler(ProjectService.AccessDeniedException.class)
    @ResponseStatus(HttpStatus.FORBIDDEN)
    public Map<String, String> forbidden(ProjectService.AccessDeniedException e) {
        return Map.of("error", "forbidden");
    }

    @ExceptionHandler(IllegalArgumentException.class)
    @ResponseStatus(HttpStatus.BAD_REQUEST)
    public Map<String, String> badRequest(IllegalArgumentException e) {
        return Map.of("error", "invalid_request");
    }

    // ------------------------------------------------------------- payloads

    public record CreateRequest(@NotBlank String name, String clientName) {
    }

    public record UpdateRequest(String name, String status) {
    }

    public record AssignRequest(@Email @NotBlank String email) {
    }

    public record ProjectResponse(long id, String name, String clientName, String status,
                                  String coverS3Key, Instant createdAt, Instant updatedAt) {
        static ProjectResponse from(Project project) {
            return new ProjectResponse(
                    project.id(), project.name(), project.clientName(), project.status(),
                    project.coverS3Key(), project.createdAt(), project.updatedAt());
        }
    }
}
