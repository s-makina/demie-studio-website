package com.demie.portal.share;

import com.demie.portal.download.DownloadService;
import com.demie.portal.media.MediaService;
import com.demie.portal.project.Project;
import com.demie.portal.project.ProjectService;
import jakarta.servlet.http.HttpServletRequest;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;

import java.util.Map;

/**
 * Share-token management (admin-only; enforced by {@code SecurityConfig} on
 * {@code POST /projects/**}) and token-URL project view + media listing
 * (public; guarded by {@link TokenAuthFilter}, which resolves the project
 * before this runs).
 */
@RestController
public class ShareController {

    private final ShareTokenService tokens;
    private final ProjectService projects;
    private final MediaService media;
    private final DownloadService downloads;

    public ShareController(ShareTokenService tokens, ProjectService projects,
                           MediaService media, DownloadService downloads) {
        this.tokens = tokens;
        this.projects = projects;
        this.media = media;
        this.downloads = downloads;
    }

    /** Photographer issues the project's share token (returned once). */
    @PostMapping("/projects/{id}/share")
    @ResponseStatus(HttpStatus.CREATED)
    public Map<String, Object> issue(@PathVariable long id) {
        ShareTokenService.IssuedToken issued = tokens.issue(id);
        return Map.of("projectId", issued.projectId(), "token", issued.rawToken());
    }

    /** Photographer rotates the token: old stops working instantly. */
    @PostMapping("/projects/{id}/share/rotate")
    public Map<String, Object> rotate(@PathVariable long id) {
        ShareTokenService.IssuedToken issued = tokens.rotate(id);
        return Map.of("projectId", issued.projectId(), "token", issued.rawToken());
    }

    /** Photographer revokes the token: denied instantly. Idempotent. */
    @PostMapping("/projects/{id}/share/revoke")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void revoke(@PathVariable long id) {
        tokens.revoke(id);
    }

    /** Token-URL project view: no login, token grants exactly its project. */
    @GetMapping("/p/{token}")
    public Map<String, Object> viewByToken(@PathVariable String token, HttpServletRequest request) {
        Object projectId = request.getAttribute(TokenAuthFilter.TOKEN_PROJECT_ATTRIBUTE);
        if (projectId == null) {
            throw new ProjectService.NotFoundException();
        }
        Project project = projects.findById((Long) projectId)
                .orElseThrow(ProjectService.NotFoundException::new);
        downloads.recordTokenView(token);
        return Map.of("id", project.id(), "name", project.name(), "status", project.status());
    }

    /** Token-URL media listing (cursor-paginated, same shape as account path). */
    @GetMapping("/p/{token}/media")
    public Map<String, Object> listByToken(@PathVariable String token,
                                           @RequestParam(required = false) String cursor,
                                           @RequestParam(required = false, defaultValue = "50") int limit) {
        MediaService.MediaPage page = media.listByToken(token, cursor, limit);
        return Map.of("items", page.items(),
                "nextCursor", page.nextCursor() == null ? "" : page.nextCursor());
    }

    // ------------------------------------------------------------- error mapping

    @ExceptionHandler(ProjectService.NotFoundException.class)
    @ResponseStatus(HttpStatus.NOT_FOUND)
    public Map<String, String> notFound(ProjectService.NotFoundException e) {
        return Map.of("error", "not_found");
    }

    @ExceptionHandler(ShareTokenService.AlreadyIssuedException.class)
    @ResponseStatus(HttpStatus.CONFLICT)
    public Map<String, String> conflict(ShareTokenService.AlreadyIssuedException e) {
        return Map.of("error", "already_issued");
    }
}
