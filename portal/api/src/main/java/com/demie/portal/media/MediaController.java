package com.demie.portal.media;

import com.demie.portal.account.Account;
import com.demie.portal.account.AccountService;
import com.demie.portal.project.ProjectService;
import com.demie.portal.s3.S3Service;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.Map;

/**
 * Upload endpoints (Task 6). Upload mutations are admin-only (see
 * {@code SecurityConfig}); listing is scoped per caller like projects.
 */
@RestController
public class MediaController {

    private final MediaService media;
    private final AccountService accounts;

    public MediaController(MediaService media, AccountService accounts) {
        this.media = media;
        this.accounts = accounts;
    }

    /** Photographer reserves a row and opens the multipart upload (idempotent per {@code clientUploadId}). */
    @PostMapping("/projects/{id}/media/init")
    @ResponseStatus(HttpStatus.CREATED)
    public MediaService.MediaInit init(@PathVariable long id,
                                       @Valid @RequestBody InitRequest request,
                                       Authentication authentication) {
        return media.initUpload(currentAccount(authentication), id,
                request.filename(), request.contentType(), request.sizeBytes(),
                request.clientUploadId());
    }

    /** Photographer gets a presigned URL for one part. */
    @PostMapping("/media/{id}/parts")
    public Map<String, Object> partUrl(@PathVariable long id,
                                       @Valid @RequestBody PartRequest request,
                                       Authentication authentication) {
        S3Service.PresignedPart part = media.partUrl(currentAccount(authentication), id,
                request.partNumber());
        return Map.of("url", part.url, "expiresAt", part.expiresAt);
    }

    /** Photographer closes the upload with the browser-collected part ETags. */
    @PostMapping("/media/{id}/complete")
    public MediaService.MediaItem complete(@PathVariable long id,
                                           @Valid @RequestBody CompleteRequest request,
                                           Authentication authentication) {
        List<MediaService.PartETag> parts = request.parts().stream()
                .map(p -> new MediaService.PartETag(p.partNumber(), p.etag()))
                .toList();
        return media.completeUpload(currentAccount(authentication), id, parts);
    }

    /** Photographer abandons an open upload (aborts MPU, drops the row). */
    @PostMapping("/media/{id}/abort")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void abort(@PathVariable long id, Authentication authentication) {
        media.abortUpload(currentAccount(authentication), id);
    }

    /** Photographer removes a finished photo (Task 21). Blocked while uploading. */
    @DeleteMapping("/media/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void delete(@PathVariable long id, Authentication authentication) {
        media.deleteMedia(currentAccount(authentication), id);
    }

    /** Cursor-paginated listing (admin or project member). */
    @GetMapping("/projects/{id}/media")
    public Map<String, Object> list(@PathVariable long id,
                                    @RequestParam(required = false) String cursor,
                                    @RequestParam(required = false) Integer offset,
                                    @RequestParam(required = false, defaultValue = "50") int limit,
                                    Authentication authentication) {
        MediaService.MediaPage page =
                media.list(currentAccount(authentication), id, cursor, limit, offset);
        return Map.of("items", page.items(),
                "nextCursor", page.nextCursor() == null ? "" : page.nextCursor(),
                "endCursor", page.endCursor() == null ? "" : page.endCursor(),
                "total", page.total());
    }

    /** One photo's fresh view (admin or project member); recovers expired URLs. */
    @GetMapping("/media/{id}")
    public MediaService.MediaView view(@PathVariable long id, Authentication authentication) {
        return media.view(currentAccount(authentication), id);
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

    /**
     * {@code clientUploadId} is the browser's stable key for this queued file
     * (one UUID per file, re-sent on retry). It drives the idempotency of
     * init and complete; omitting it fails validation.
     */
    public record InitRequest(String filename, @NotBlank String contentType,
                              @NotNull @Min(0) Long sizeBytes,
                              @NotBlank String clientUploadId) {
    }

    public record PartRequest(@Min(1) int partNumber) {
    }

    public record CompleteRequest(List<PartPayload> parts) {
    }

    public record PartPayload(int partNumber, String etag) {
    }
}
