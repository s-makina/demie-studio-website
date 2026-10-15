package com.demie.portal.account;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;
import org.springframework.http.HttpStatus;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.AuthenticationException;
import org.springframework.security.core.context.SecurityContext;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.web.context.HttpSessionSecurityContextRepository;
import org.springframework.security.web.context.SecurityContextRepository;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.ResponseStatus;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.bind.annotation.GetMapping;

import java.time.Instant;
import java.util.Map;

@RestController
@RequestMapping("/auth")
public class AuthController {

    private final AccountService accounts;
    private final AuthenticationManager authenticationManager;
    private final SecurityContextRepository securityContextRepository =
            new HttpSessionSecurityContextRepository();

    public AuthController(AccountService accounts, AuthenticationManager authenticationManager) {
        this.accounts = accounts;
        this.authenticationManager = authenticationManager;
    }

    /** Photographer/client sign-in. Establishes a session on success. */
    @PostMapping("/login")
    public AccountResponse login(@Valid @RequestBody LoginRequest request,
                                 HttpServletRequest servletRequest,
                                 HttpServletResponse servletResponse) {
        Authentication authentication = authenticationManager.authenticate(
                new UsernamePasswordAuthenticationToken(request.email(), request.password()));

        SecurityContext context = SecurityContextHolder.createEmptyContext();
        context.setAuthentication(authentication);
        SecurityContextHolder.setContext(context);
        securityContextRepository.saveContext(context, servletRequest, servletResponse);

        Account account = accounts.findByEmail(authentication.getName()).orElseThrow();
        return AccountResponse.from(account);
    }

    /** Admin-only: invite a client by email (creates/links a pending account). */
    @PostMapping("/invite")
    public InviteResponse invite(@Valid @RequestBody InviteRequest request) {
        AccountService.Invite invite = accounts.inviteClient(request.email(), request.name());
        return new InviteResponse(
                invite.accountId(),
                invite.email(),
                invite.rawToken(),
                invite.expiresAt(),
                invite.tokenIssued());
    }

    /** Public: accept an invite by setting a password. */
    @PostMapping("/invite/accept")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void acceptInvite(@Valid @RequestBody AcceptInviteRequest request) {
        accounts.acceptInvite(request.token(), request.password());
    }

    /** Current session's account. */
    @GetMapping("/me")
    public AccountResponse me(Authentication authentication) {
        Account account = accounts.findByEmail(authentication.getName()).orElseThrow();
        return AccountResponse.from(account);
    }

    // ------------------------------------------------------------- error mapping

    @ExceptionHandler(AuthenticationException.class)
    @ResponseStatus(HttpStatus.UNAUTHORIZED)
    public Map<String, String> unauthorized(AuthenticationException e) {
        return Map.of("error", "invalid_credentials");
    }

    @ExceptionHandler(AccountService.InvalidInviteException.class)
    @ResponseStatus(HttpStatus.BAD_REQUEST)
    public Map<String, String> invalidInvite(AccountService.InvalidInviteException e) {
        return Map.of("error", "invalid_invite");
    }

    // ------------------------------------------------------------- payloads

    public record LoginRequest(@Email @NotBlank String email, @NotBlank String password) {
    }

    public record InviteRequest(@Email @NotBlank String email, String name) {
    }

    public record AcceptInviteRequest(@NotBlank String token, @Size(min = 8) String password) {
    }

    public record AccountResponse(long id, String email, String name, String role, String status) {
        static AccountResponse from(Account account) {
            return new AccountResponse(
                    account.id(), account.email(), account.name(), account.role(), account.status());
        }
    }

    public record InviteResponse(long accountId, String email, String inviteToken,
                                 Instant expiresAt, boolean tokenIssued) {
    }
}
