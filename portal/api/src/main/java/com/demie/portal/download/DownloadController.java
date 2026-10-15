package com.demie.portal.download;

import com.demie.portal.account.Account;
import com.demie.portal.account.AccountService;
import com.demie.portal.project.ProjectService;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;

import java.net.URI;
import java.util.Map;

/**
 * Per-file download endpoints (Task 10). Both paths answer {@code 302}
 * straight to a presigned S3 GET of the original — bytes never transit the
 * API. The {@code Content-Disposition} is conveyed by the presigned URL's
 * filename; browsers follow the redirect and save the file.
 */
@RestController
public class DownloadController {

    private final DownloadService downloads;
    private final AccountService accounts;

    public DownloadController(DownloadService downloads, AccountService accounts) {
        this.downloads = downloads;
        this.accounts = accounts;
    }

    /** Logged-in download of one file. */
    @GetMapping("/media/{id}/download")
    public ResponseEntity<Void> download(@PathVariable long id, Authentication authentication) {
        DownloadService.DownloadTarget target =
                downloads.downloadForAccount(currentAccount(authentication), id);
        return redirect(target);
    }

    /** Token-URL download of one file (no login). */
    @GetMapping("/p/{token}/media/{id}/download")
    public ResponseEntity<Void> downloadByToken(@PathVariable String token,
                                                @PathVariable long id) {
        return redirect(downloads.downloadForToken(token, id));
    }

    private static ResponseEntity<Void> redirect(DownloadService.DownloadTarget target) {
        return ResponseEntity.status(HttpStatus.FOUND)
                .location(URI.create(target.url()))
                .build();
    }

    private Account currentAccount(Authentication authentication) {
        return accounts.findByEmail(authentication.getName()).orElseThrow(
                ProjectService.NotFoundException::new);
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
}
