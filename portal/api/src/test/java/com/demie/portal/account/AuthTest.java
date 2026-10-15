package com.demie.portal.account;

import com.fasterxml.jackson.databind.ObjectMapper;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.mock.web.MockHttpSession;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.MvcResult;

import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class AuthTest {

    private static final String ADMIN_EMAIL = "photographer@demie.test";
    private static final String ADMIN_PASSWORD = "s3cret-pass";

    @Autowired
    private MockMvc mvc;

    @Autowired
    private ObjectMapper json;

    @Autowired
    private JdbcTemplate jdbc;

    @Autowired
    private AccountService accounts;

    @BeforeEach
    void reset() {
        jdbc.execute("TRUNCATE account, project, project_member, media, share_token, " +
                "download_event, zip_job RESTART IDENTITY CASCADE");
        accounts.createAdmin(ADMIN_EMAIL, ADMIN_PASSWORD, "Demie");
    }

    @Test
    void photographerCanLogIn() throws Exception {
        mvc.perform(post("/auth/login")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", ADMIN_EMAIL, "password", ADMIN_PASSWORD))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.role").value("admin"))
                .andExpect(jsonPath("$.status").value("active"));
    }

    @Test
    void loginFailsWithWrongPassword() throws Exception {
        mvc.perform(post("/auth/login")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", ADMIN_EMAIL, "password", "wrong"))))
                .andExpect(status().isUnauthorized());
    }

    @Test
    void inviteCreatesPendingClientAccount() throws Exception {
        MockHttpSession session = login(ADMIN_EMAIL, ADMIN_PASSWORD);

        mvc.perform(post("/auth/invite")
                        .session(session)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", "client@example.com", "name", "Client"))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.email").value("client@example.com"))
                .andExpect(jsonPath("$.tokenIssued").value(true));

        Account client = accounts.findByEmail("client@example.com").orElseThrow();
        assertEquals("pending", client.status());
        assertEquals("client", client.role());
        assertTrue(client.passwordHash().isBlank(), "pending account has no usable password");
    }

    @Test
    void inviteIsAdminOnly() throws Exception {
        // Unauthenticated caller cannot invite.
        MvcResult anonymous = mvc.perform(post("/auth/invite")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", "sneaky@example.com"))))
                .andReturn();
        int anonymousStatus = anonymous.getResponse().getStatus();
        assertTrue(anonymousStatus == 401 || anonymousStatus == 403,
                "anonymous invite must be denied; got HTTP " + anonymousStatus);
        assertTrue(accounts.findByEmail("sneaky@example.com").isEmpty());
    }

    @Test
    void thereIsNoPublicSignupEndpoint() throws Exception {
        MvcResult result = mvc.perform(post("/auth/register")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", "anyone@example.com", "password", "whatever"))))
                .andReturn();

        int status = result.getResponse().getStatus();
        assertTrue(status == 404 || status == 403,
                "no self-registration endpoint should exist; got HTTP " + status);
        assertTrue(accounts.findByEmail("anyone@example.com").isEmpty(),
                "a signup attempt must not create an account");
    }

    @Test
    void inviteAcceptActivatesAccountAndClientCanLogIn() throws Exception {
        MockHttpSession session = login(ADMIN_EMAIL, ADMIN_PASSWORD);

        String inviteToken = inviteAndReadToken(session, "family@example.com");

        mvc.perform(post("/auth/invite/accept")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("token", inviteToken, "password", "new-pass-123"))))
                .andExpect(status().isNoContent());

        Account activated = accounts.findByEmail("family@example.com").orElseThrow();
        assertEquals("active", activated.status());
        assertNull(activated.inviteToken(), "invite token is consumed on accept");

        mvc.perform(post("/auth/login")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", "family@example.com", "password", "new-pass-123"))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.role").value("client"));
    }

    @Test
    void inviteForExistingActiveAccountLinksWithoutNewToken() throws Exception {
        MockHttpSession session = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        String inviteToken = inviteAndReadToken(session, "returning@example.com");

        mvc.perform(post("/auth/invite/accept")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("token", inviteToken, "password", "new-pass-123"))))
                .andExpect(status().isNoContent());

        // Inviting the now-active client again links the existing account
        // rather than creating a duplicate or issuing a new token.
        mvc.perform(post("/auth/invite")
                        .session(session)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", "returning@example.com"))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.tokenIssued").value(false));

        assertEquals(1, accounts.findByEmail("returning@example.com").stream().count());
        assertTrue(accounts.findByEmail("returning@example.com").isPresent());
    }

    @Test
    void acceptInviteRejectsUnknownToken() throws Exception {
        mvc.perform(post("/auth/invite/accept")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("token", "not-a-real-token", "password", "new-pass-123"))))
                .andExpect(status().isBadRequest());
    }

    @Test
    void pendingAccountCannotLogInBeforeAcceptingInvite() throws Exception {
        MockHttpSession session = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        inviteAndReadToken(session, "notyet@example.com");

        mvc.perform(post("/auth/login")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", "notyet@example.com", "password", "anything"))))
                .andExpect(status().isUnauthorized());
    }

    // ------------------------------------------------------------- helpers

    private String inviteAndReadToken(MockHttpSession session, String email) throws Exception {
        MvcResult result = mvc.perform(post("/auth/invite")
                        .session(session)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", email))))
                .andExpect(status().isOk())
                .andReturn();
        String token = json.readTree(result.getResponse().getContentAsString())
                .get("inviteToken").asText();
        assertFalse(token.isBlank(), "an invite token should be issued");
        return token;
    }

    private MockHttpSession login(String email, String password) throws Exception {
        MvcResult result = mvc.perform(post("/auth/login")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", email, "password", password))))
                .andReturn();
        assertEquals(200, result.getResponse().getStatus(), "login should succeed for test setup");
        return (MockHttpSession) result.getRequest().getSession(false);
    }

    private String body(Object value) throws Exception {
        return json.writeValueAsString(value);
    }
}
