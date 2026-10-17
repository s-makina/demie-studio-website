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

import java.util.Arrays;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class ShareTokenTest {

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
    void adminCanIssueTokenAndOnlyHashIsStored() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Ama Wedding");

        MvcResult result = mvc.perform(post("/projects/" + projectId + "/share").session(admin))
                .andExpect(status().isCreated())
                .andExpect(jsonPath("$.token").isString())
                .andReturn();
        String rawToken = json.readTree(result.getResponse().getContentAsString())
                .get("token").asText();
        assertEquals(43, rawToken.length(), "256-bit token is 43 base64url chars");

        byte[] stored = jdbc.queryForObject(
                "SELECT token_hash FROM share_token WHERE project_id = ?", byte[].class, projectId);
        assertEquals(32, stored.length, "SHA-256 hash stored, not the raw token");
        assertTrue(!Arrays.equals(stored, rawToken.getBytes()),
                "stored bytes must differ from the raw token");
    }

    @Test
    void issueTwiceConflicts() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Double Issue");

        mvc.perform(post("/projects/" + projectId + "/share").session(admin))
                .andExpect(status().isCreated());
        mvc.perform(post("/projects/" + projectId + "/share").session(admin))
                .andExpect(status().isConflict());
    }

    @Test
    void tokenGrantsProjectViewWithoutLogin() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Open House");
        String token = issueToken(admin, projectId);

        mvc.perform(get("/p/" + token))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.name").value("Open House"))
                .andExpect(jsonPath("$.id").value(projectId));
    }

    @Test
    void unknownTokenDenied() throws Exception {
        mvc.perform(get("/p/not-a-real-token"))
                .andExpect(status().isNotFound());
    }

    @Test
    void rotateInvalidatesOldToken() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Rotation");
        String oldToken = issueToken(admin, projectId);

        MvcResult rotated = mvc.perform(post("/projects/" + projectId + "/share/rotate").session(admin))
                .andExpect(status().isOk())
                .andReturn();
        String newToken = json.readTree(rotated.getResponse().getContentAsString())
                .get("token").asText();

        mvc.perform(get("/p/" + oldToken)).andExpect(status().isNotFound());
        mvc.perform(get("/p/" + newToken))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.name").value("Rotation"));
    }

    @Test
    void revokeDeniesInstantly() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Revoked");
        String token = issueToken(admin, projectId);

        mvc.perform(get("/p/" + token)).andExpect(status().isOk());

        mvc.perform(post("/projects/" + projectId + "/share/revoke").session(admin))
                .andExpect(status().isNoContent());

        mvc.perform(get("/p/" + token)).andExpect(status().isNotFound());

        // Revoke is idempotent.
        mvc.perform(post("/projects/" + projectId + "/share/revoke").session(admin))
                .andExpect(status().isNoContent());
    }

    @Test
    void tokenSeesOnlyItsOwnProject() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        createProject(admin, "Project A");
        long projectB = createProject(admin, "Project B");
        String tokenB = issueToken(admin, projectB);

        // The token URL exposes exactly its project — never the other one.
        mvc.perform(get("/p/" + tokenB))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.name").value("Project B"))
                .andExpect(jsonPath("$.id").value(projectB));
    }

    @Test
    void tokenMediaItemStaysInsideItsProject() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectA = createProject(admin, "Project A");
        long projectB = createProject(admin, "Project B");
        String tokenB = issueToken(admin, projectB);

        long mine = insertMedia(projectB);
        long foreign = insertMedia(projectA);

        // The token URL serves a fresh, browser-safe view of its own media...
        mvc.perform(get("/p/" + tokenB + "/media/" + mine))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.id").value(mine))
                .andExpect(jsonPath("$.thumbWidth").value(400));

        // ...and never reaches media belonging to another project.
        mvc.perform(get("/p/" + tokenB + "/media/" + foreign))
                .andExpect(status().isNotFound());

        // Revoking the token closes the single-item route too.
        mvc.perform(post("/projects/" + projectB + "/share/revoke").session(admin))
                .andExpect(status().isNoContent());
        mvc.perform(get("/p/" + tokenB + "/media/" + mine))
                .andExpect(status().isNotFound());
    }

    @Test
    void clientCannotManageShareTokens() throws Exception {
        accounts.inviteClient("client@example.com", "Client");
        String token = accounts.inviteClient("client@example.com", "Client").rawToken();
        mvc.perform(post("/auth/invite/accept")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("token", token, "password", "client-pass-123"))))
                .andExpect(status().isNoContent());
        MockHttpSession client = login("client@example.com", "client-pass-123");

        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Guarded");

        mvc.perform(post("/projects/" + projectId + "/share").session(client))
                .andExpect(status().isForbidden());
    }

    @Test
    void tokenProbesAreThrottled() throws Exception {
        // Dedicated source IP so other tests' probes never interfere.
        int throttled = 0;
        for (int i = 0; i < 70; i++) {
            MvcResult result = mvc.perform(get("/p/probe-" + i)
                            .with(request -> {
                                request.setRemoteAddr("10.9.9.9");
                                return request;
                            }))
                    .andReturn();
            if (result.getResponse().getStatus() == 429) {
                throttled++;
            }
        }
        assertTrue(throttled > 0,
                "sustained probing from one IP must eventually return 429");
    }

    // ------------------------------------------------------------- helpers

    private long createProject(MockHttpSession admin, String name) throws Exception {
        MvcResult result = mvc.perform(post("/projects")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("name", name))))
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

    /** Derivative-pending row, so listing stays free of S3 presigning. */
    private long insertMedia(long projectId) {
        return jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, " +
                        "derivative_status, thumb_width, thumb_height) " +
                        "VALUES (?, ?, 10, 'photo', 1, 'uploaded', 'pending', 400, 300) " +
                        "RETURNING id",
                Long.class, projectId, "originals/" + projectId + "/1");
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
