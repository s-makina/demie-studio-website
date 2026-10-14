package com.demie.portal.share;

import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.stereotype.Component;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;
import java.util.ArrayDeque;
import java.util.Deque;
import java.util.List;
import java.util.Optional;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Bearer guard for token URLs ({@code /p/<token>/...}). The first path
 * segment after {@code /p/} is the raw share token; valid, non-revoked
 * tokens authenticate the request for exactly their project (exposed as the
 * {@code tokenProjectId} request attribute). Unknown/revoked tokens get 404
 * (no validity oracle); probing is throttled per client IP (429).
 */
@Component
public class TokenAuthFilter extends OncePerRequestFilter {

    /** Request attribute carrying the resolved project id for {@code /p/**}. */
    public static final String TOKEN_PROJECT_ATTRIBUTE = "tokenProjectId";

    static final int MAX_PROBES_PER_MINUTE_PER_IP = 60;
    private static final long WINDOW_MILLIS = 60_000L;

    private final ShareTokenService tokens;
    private final ConcurrentHashMap<String, Deque<Long>> probesByIp = new ConcurrentHashMap<>();

    public TokenAuthFilter(ShareTokenService tokens) {
        this.tokens = tokens;
    }

    @Override
    protected boolean shouldNotFilter(HttpServletRequest request) {
        return !request.getRequestURI().startsWith("/p/");
    }

    @Override
    protected void doFilterInternal(HttpServletRequest request, HttpServletResponse response,
                                    FilterChain chain) throws ServletException, IOException {
        String remainder = request.getRequestURI().substring("/p/".length());
        String rawToken = remainder.contains("/") ? remainder.substring(0, remainder.indexOf('/'))
                : remainder;

        if (!allowProbe(clientIp(request))) {
            // 429 — jakarta.servlet has no SC_TOO_MANY_REQUESTS constant.
            writeError(response, 429, "rate_limited");
            return;
        }

        Optional<ShareTokenService.TokenGrant> grant = tokens.resolve(rawToken);
        if (grant.isEmpty()) {
            writeError(response, HttpServletResponse.SC_NOT_FOUND, "not_found");
            return;
        }

        request.setAttribute(TOKEN_PROJECT_ATTRIBUTE, grant.get().projectId());
        var authentication = new UsernamePasswordAuthenticationToken(
                "token:" + grant.get().tokenId(), null,
                List.of(new SimpleGrantedAuthority("ROLE_TOKEN")));
        SecurityContextHolder.getContext().setAuthentication(authentication);
        try {
            chain.doFilter(request, response);
        } finally {
            SecurityContextHolder.clearContext();
        }
    }

    private boolean allowProbe(String ip) {
        long now = System.currentTimeMillis();
        Deque<Long> probes = probesByIp.computeIfAbsent(ip, key -> new ArrayDeque<>());
        synchronized (probes) {
            while (!probes.isEmpty() && now - probes.peekFirst() > WINDOW_MILLIS) {
                probes.pollFirst();
            }
            if (probes.size() >= MAX_PROBES_PER_MINUTE_PER_IP) {
                return false;
            }
            probes.addLast(now);
            return true;
        }
    }

    private static String clientIp(HttpServletRequest request) {
        String remote = request.getRemoteAddr();
        return remote == null ? "unknown" : remote;
    }

    private static void writeError(HttpServletResponse response, int status, String error)
            throws IOException {
        response.setStatus(status);
        response.setContentType("application/json");
        response.getWriter().write("{\"error\":\"" + error + "\"}");
    }
}
