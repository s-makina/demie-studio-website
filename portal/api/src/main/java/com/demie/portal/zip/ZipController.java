package com.demie.portal.zip;

import com.demie.portal.account.Account;
import com.demie.portal.account.AccountService;
import com.demie.portal.project.ProjectService;
import com.fasterxml.jackson.annotation.JsonProperty;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;

import java.net.URI;
import java.util.List;
import java.util.Map;

/**
 * ZIP endpoints (Task 11). Both access paths share the shape: POST returns
 * {@code 202 + job_id} (idempotent per selection — a live job for the same
 * files is resumed, never duplicated), GET polls the state machine, and the
 * {@code /download} URL 302s to a presigned save-as GET once ready. Bytes
 * never transit the API.
 */
@RestController
public class ZipController {

    private final ZipJobService zips;
    private final AccountService accounts;

    public ZipController(ZipJobService zips, AccountService accounts) {
        this.zips = zips;
        this.accounts = accounts;
    }

    /** Logged-in ZIP request (all uploaded media, or an explicit subset). */
    @PostMapping("/projects/{id}/zip")
    public ResponseEntity<Map<String, Object>> request(@PathVariable long id,
                                                       @RequestBody(required = false) ZipRequest body,
                                                       Authentication authentication) {
        ZipJob job = zips.requestForAccount(currentAccount(authentication), id,
                body == null ? null : body.mediaIds());
        return ResponseEntity.status(HttpStatus.ACCEPTED)
                .body(Map.of("job_id", job.id(), "status", job.status()));
    }

    /** Logged-in job poll, scoped to the path project. */
    @GetMapping("/projects/{id}/zip/{jobId}")
    public ZipJobService.ZipJobView poll(@PathVariable long id, @PathVariable long jobId,
                                         Authentication authentication) {
        return zips.getForAccount(currentAccount(authentication), id, jobId);
    }

    /** Logged-in ZIP download: 302 to the finished object. */
    @GetMapping("/projects/{id}/zip/{jobId}/download")
    public ResponseEntity<Void> download(@PathVariable long id, @PathVariable long jobId,
                                         Authentication authentication) {
        return redirect(zips.downloadForAccount(currentAccount(authentication), id, jobId));
    }

    /** Token-URL ZIP request (no login). */
    @PostMapping("/p/{token}/zip")
    public ResponseEntity<Map<String, Object>> requestByToken(@PathVariable String token,
                                                              @RequestBody(required = false) ZipRequest body) {
        ZipJob job = zips.requestForToken(token, body == null ? null : body.mediaIds());
        return ResponseEntity.status(HttpStatus.ACCEPTED)
                .body(Map.of("job_id", job.id(), "status", job.status()));
    }

    /** Token-URL job poll (no login). */
    @GetMapping("/p/{token}/zip/{jobId}")
    public ZipJobService.ZipJobView pollByToken(@PathVariable String token,
                                                @PathVariable long jobId) {
        return zips.getForToken(token, jobId);
    }

    /** Token-URL ZIP download (no login): 302 to the finished object. */
    @GetMapping("/p/{token}/zip/{jobId}/download")
    public ResponseEntity<Void> downloadByToken(@PathVariable String token,
                                                @PathVariable long jobId) {
        return redirect(zips.downloadForToken(token, jobId));
    }

    private static ResponseEntity<Void> redirect(ZipJobService.ZipDownload target) {
        return ResponseEntity.status(HttpStatus.FOUND)
                .location(URI.create(target.url()))
                .build();
    }

    private Account currentAccount(Authentication authentication) {
        return accounts.findByEmail(authentication.getName()).orElseThrow(
                ProjectService.NotFoundException::new);
    }

    /** {@code {media_ids?}} — absent/empty means all uploaded media. */
    public record ZipRequest(@JsonProperty("media_ids") List<Long> mediaIds) {
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

    @ExceptionHandler(ZipJobService.NotReadyException.class)
    @ResponseStatus(HttpStatus.CONFLICT)
    public Map<String, String> notReady(ZipJobService.NotReadyException e) {
        return Map.of("error", "not_ready");
    }
}
