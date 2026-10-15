package com.demie.portal.download;

import com.demie.portal.account.AccountService;
import com.fasterxml.jackson.databind.JsonNode;
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

import java.util.List;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

/**
 * Task 10 verify: per-file download is a 302 to a presigned original on
 * both access paths, and every view/download writes a
 * {@code download_event} row so per-project counts increment.
 */
@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class AnalyticsTest {

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
    void accountViewAndDownloadRecordEvents() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set (signing only)");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Gallery Night");
        long mediaId = insertMedia(projectId, "originals/" + projectId + "/1/photo.jpg", "ready");

        mvc.perform(get("/projects/" + projectId).session(admin))
                .andExpect(status().isOk());

        MvcResult download = mvc.perform(get("/media/" + mediaId + "/download").session(admin))
                .andExpect(status().isFound())
                .andReturn();
        String location = download.getResponse().getHeader("Location");
        assertNotNull(location, "download must 302 to a presigned URL");
        assertTrue(location.contains("photo.jpg"), "redirect targets the original, got: " + location);
        assertTrue(location.contains("response-content-disposition"),
                "redirect forces save-as, got: " + location);

        assertEquals(1, countEvents(projectId, "view"), "project view recorded");
        assertEquals(1, countEvents(projectId, "download"), "download recorded");

        Map<String, Object> event = jdbc.queryForMap(
                "SELECT account_id, token_id, media_id FROM download_event " +
                        "WHERE project_id = ? AND event_type = 'download'", projectId);
        assertNotNull(event.get("account_id"), "account download links the account");
        assertNull(event.get("token_id"), "account download has no token");
        assertEquals(mediaId, ((Number) event.get("media_id")).longValue());
    }

    @Test
    void tokenViewListingAndDownloadRecordEvents() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set (signing only)");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Token Gallery");
        long readyId = insertMedia(projectId, "originals/" + projectId + "/1/ready.jpg", "ready");
        insertMedia(projectId, "originals/" + projectId + "/2/pending.jpg", "pending");
        String token = issueToken(admin, projectId);

        mvc.perform(get("/p/" + token)).andExpect(status().isOk());

        MvcResult listing = mvc.perform(get("/p/" + token + "/media"))
                .andExpect(status().isOk())
                .andReturn();
        JsonNode items = json.readTree(listing.getResponse().getContentAsString()).get("items");
        assertEquals(2, items.size(), "token listing shows the project media");
        for (JsonNode item : items) {
            assertTrue(item.get("id").isNumber(), "listing item has an id");
            assertTrue(item.get("s3Key") == null && item.get("s3_key") == null,
                    "listing must not leak internal S3 keys");
            if (item.get("id").asLong() == readyId) {
                assertTrue(item.get("thumbUrl").asText().startsWith("http"),
                        "ready derivative gets a presigned thumb URL");
            } else {
                assertTrue(item.get("thumbUrl").isNull(),
                        "pending derivative has no thumb URL yet");
            }
        }

        MvcResult download = mvc.perform(get("/p/" + token + "/media/" + readyId + "/download"))
                .andExpect(status().isFound())
                .andReturn();
        assertTrue(download.getResponse().getHeader("Location").contains("ready.jpg"),
                "token download 302s to the original");

        assertEquals(1, countEvents(projectId, "view"), "token project view recorded");
        assertEquals(1, countEvents(projectId, "download"), "token download recorded");

        Map<String, Object> event = jdbc.queryForMap(
                "SELECT account_id, token_id FROM download_event " +
                        "WHERE project_id = ? AND event_type = 'download'", projectId);
        assertNull(event.get("account_id"), "token download has no account");
        assertNotNull(event.get("token_id"), "token download links the token");
    }

    @Test
    void strangerAccountCannotDownload() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Private Shoot");
        long mediaId = insertMedia(projectId, "originals/" + projectId + "/1/secret.jpg", "ready");

        // A client account with no membership in the project.
        AccountService.Invite invite = accounts.inviteClient("stranger@demie.test", "Stranger");
        accounts.acceptInvite(invite.rawToken(), "stranger-pass");
        MockHttpSession stranger = login("stranger@demie.test", "stranger-pass");

        mvc.perform(get("/media/" + mediaId + "/download").session(stranger))
                .andExpect(status().isForbidden());
        assertEquals(0, countEvents(projectId, "download"), "denied download writes no event");
    }

    @Test
    void unknownAndCrossProjectTokenDownloadsAreNotFound() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectA = createProject(admin, "Project A");
        long projectB = createProject(admin, "Project B");
        long mediaB = insertMedia(projectB, "originals/" + projectB + "/1/other.jpg", "ready");
        String tokenA = issueToken(admin, projectA);

        mvc.perform(get("/p/nope-not-real/media/" + mediaB + "/download"))
                .andExpect(status().isNotFound());
        mvc.perform(get("/p/" + tokenA + "/media/" + mediaB + "/download"))
                .andExpect(status().isNotFound());
        assertEquals(0, countEvents(projectB, "download"), "denied download writes no event");
    }

    @Test
    void countsIncrementPerProject() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set (signing only)");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Counted");
        long mediaId = insertMedia(projectId, "originals/" + projectId + "/1/one.jpg", "ready");
        String token = issueToken(admin, projectId);

        mvc.perform(get("/projects/" + projectId).session(admin)).andExpect(status().isOk());
        mvc.perform(get("/p/" + token)).andExpect(status().isOk());
        mvc.perform(get("/media/" + mediaId + "/download").session(admin))
                .andExpect(status().isFound());
        mvc.perform(get("/p/" + token + "/media/" + mediaId + "/download"))
                .andExpect(status().isFound());

        List<Map<String, Object>> counts = jdbc.queryForList(
                "SELECT event_type, COUNT(*) AS n FROM download_event " +
                        "WHERE project_id = ? GROUP BY event_type", projectId);
        assertEquals(2, counts.stream()
                        .filter(r -> "view".equals(r.get("event_type")))
                        .mapToLong(r -> ((Number) r.get("n")).longValue()).sum(),
                "two views (account + token)");
        assertEquals(2, counts.stream()
                        .filter(r -> "download".equals(r.get("event_type")))
                        .mapToLong(r -> ((Number) r.get("n")).longValue()).sum(),
                "two downloads (account + token)");
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

    private long insertMedia(long projectId, String key, String derivativeStatus) {
        return jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, " +
                        "derivative_status) " +
                        "VALUES (?, ?, 1024, 'photo', 1, 'uploaded', ?) RETURNING id",
                Long.class, projectId, key, derivativeStatus);
    }

    private int countEvents(long projectId, String eventType) {
        Integer count = jdbc.queryForObject(
                "SELECT COUNT(*) FROM download_event WHERE project_id = ? AND event_type = ?",
                Integer.class, projectId, eventType);
        return count == null ? 0 : count;
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
