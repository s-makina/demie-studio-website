package com.demie.portal.share;

import com.demie.portal.account.AccountService;
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
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

/**
 * Task 14 verify: photographer share status + per-project counts; client
 * cannot read them; revoked token denies instantly.
 */
@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class ShareStatsTest {

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
    void shareStatusLifecycle() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Stats Shoot");

        mvc.perform(get("/projects/" + projectId + "/share/status").session(admin))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.issued").value(false))
                .andExpect(jsonPath("$.revoked").value(false));

        String token = issueToken(admin, projectId);

        mvc.perform(get("/projects/" + projectId + "/share/status").session(admin))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.issued").value(true))
                .andExpect(jsonPath("$.revoked").value(false));

        mvc.perform(get("/p/" + token)).andExpect(status().isOk());

        mvc.perform(post("/projects/" + projectId + "/share/revoke").session(admin))
                .andExpect(status().isNoContent());

        mvc.perform(get("/projects/" + projectId + "/share/status").session(admin))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.issued").value(true))
                .andExpect(jsonPath("$.revoked").value(true));

        // Revoked token denies instantly (404, no oracle).
        mvc.perform(get("/p/" + token)).andExpect(status().isNotFound());
    }

    @Test
    void statsCountViewsAndDownloads() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set (signing only)");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Counted");
        long mediaId = jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, derivative_status) " +
                        "VALUES (?, ?, 1024, 'photo', 1, 'uploaded', 'ready') RETURNING id",
                Long.class, projectId, "originals/" + projectId + "/1/one.jpg");
        String token = issueToken(admin, projectId);

        mvc.perform(get("/projects/" + projectId + "/stats").session(admin))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.views").value(0))
                .andExpect(jsonPath("$.downloads").value(0));

        mvc.perform(get("/projects/" + projectId).session(admin)).andExpect(status().isOk());
        mvc.perform(get("/p/" + token)).andExpect(status().isOk());
        mvc.perform(get("/media/" + mediaId + "/download").session(admin))
                .andExpect(status().isFound());
        mvc.perform(get("/p/" + token + "/media/" + mediaId + "/download"))
                .andExpect(status().isFound());

        mvc.perform(get("/projects/" + projectId + "/stats").session(admin))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.views").value(2))
                .andExpect(jsonPath("$.downloads").value(2));
    }

    @Test
    void clientCannotReadStatsOrShareStatus() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Guarded");

        AccountService.Invite invite = accounts.inviteClient("client@demie.test", "Client");
        accounts.acceptInvite(invite.rawToken(), "client-pass-123");
        MockHttpSession client = login("client@demie.test", "client-pass-123");

        mvc.perform(get("/projects/" + projectId + "/stats").session(client))
                .andExpect(status().isForbidden());
        mvc.perform(get("/projects/" + projectId + "/share/status").session(client))
                .andExpect(status().isForbidden());
    }

    @Test
    void unknownProjectStatsIsNotFound() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        mvc.perform(get("/projects/999999/stats").session(admin))
                .andExpect(status().isNotFound());
        mvc.perform(get("/projects/999999/share/status").session(admin))
                .andExpect(status().isNotFound());
    }

    // ------------------------------------------------------------- helpers

    private static boolean s3Available() {
        return envPresent("S3_ENDPOINT") && envPresent("S3_ACCESS_KEY")
                && envPresent("S3_SECRET_KEY");
    }

    private static boolean envPresent(String name) {
        String value = System.getenv(name);
        return value != null && !value.isBlank();
    }

    private long createProject(MockHttpSession admin, String name) throws Exception {
        MvcResult result = mvc.perform(post("/projects")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(Map.of("name", name))))
                .andExpect(status().isCreated())
                .andReturn();
        return json.readTree(result.getResponse().getContentAsString()).get("id").asLong();
    }

    private String issueToken(MockHttpSession admin, long projectId) throws Exception {
        MvcResult result = mvc.perform(post("/projects/" + projectId + "/share").session(admin))
                .andExpect(status().isCreated())
                .andReturn();
        return json.readTree(result.getResponse().getContentAsString()).get("token").asText();
    }

    private MockHttpSession login(String email, String password) throws Exception {
        MvcResult result = mvc.perform(post("/auth/login")
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(Map.of("email", email, "password", password))))
                .andReturn();
        assertEquals(200, result.getResponse().getStatus(), "login should succeed for test setup");
        return (MockHttpSession) result.getRequest().getSession(false);
    }
}
