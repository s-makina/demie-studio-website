package com.demie.portal.zip;

import com.demie.portal.account.AccountService;
import com.demie.portal.project.ProjectService;
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

import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

/**
 * Task 11 verify: {@code POST zip → 202 + job_id},
 * {@code pending → running → ready → expired} polling, idempotent resume per
 * selection, expiry + rebuild, and the same access model as downloads on both
 * paths (member clients may request; strangers/cross-project read as
 * denied/not-found).
 */
@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class ZipJobTest {

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

    @Autowired
    private ProjectService projects;

    @BeforeEach
    void reset() {
        jdbc.execute("TRUNCATE account, project, project_member, media, share_token, " +
                "download_event, zip_job RESTART IDENTITY CASCADE");
        accounts.createAdmin(ADMIN_EMAIL, ADMIN_PASSWORD, "Demie");
    }

    @Test
    void requestAllReturnsPendingAndPolls() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Gallery Night");
        insertMedia(projectId, "originals/1/a.jpg", "uploaded", 1);
        insertMedia(projectId, "originals/1/b.jpg", "uploaded", 2);

        long jobId = requestZip(admin, projectId, "{}");
        JsonNode polled = poll(admin, "/projects/" + projectId + "/zip/" + jobId);
        assertEquals("pending", polled.get("status").asText());
        assertEquals(2, polled.get("mediaCount").asInt(), "empty selection means all uploaded");
        assertTrue(polled.get("sizeBytes").isNull(), "no size before the build");
    }

    @Test
    void requestSubsetValidatesSelection() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Subset");
        long keep = insertMedia(projectId, "originals/1/keep.jpg", "uploaded", 1);
        insertMedia(projectId, "originals/1/skip.jpg", "uploaded", 2);
        long uploading = insertMedia(projectId, "originals/1/open.jpg", "uploading", 3);
        long otherProject = createProject(admin, "Other");
        long foreign = insertMedia(otherProject, "originals/2/x.jpg", "uploaded", 1);

        long jobId = requestZip(admin, projectId,
                json.writeValueAsString(Map.of("media_ids", new long[]{keep})));
        assertEquals(1, poll(admin, "/projects/" + projectId + "/zip/" + jobId)
                .get("mediaCount").asInt(), "subset ZIP covers exactly the selected file");

        mvc.perform(post("/projects/" + projectId + "/zip").session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(Map.of("media_ids", new long[]{99999}))))
                .andExpect(status().isNotFound());

        mvc.perform(post("/projects/" + projectId + "/zip").session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(Map.of("media_ids", new long[]{foreign}))))
                .andExpect(status().isNotFound());

        mvc.perform(post("/projects/" + projectId + "/zip").session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(Map.of("media_ids", new long[]{uploading}))))
                .andExpect(status().isBadRequest());
    }

    @Test
    void duplicateRequestResumesSameJob() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Resume");
        long first = insertMedia(projectId, "originals/1/a.jpg", "uploaded", 1);
        insertMedia(projectId, "originals/1/b.jpg", "uploaded", 2);

        long all1 = requestZip(admin, projectId, "{}");
        assertEquals(all1, requestZip(admin, projectId, null),
                "repeat all-request resumes the live job (no body)");

        String subset = json.writeValueAsString(Map.of("media_ids", new long[]{first}));
        long sub1 = requestZip(admin, projectId, subset);
        assertEquals(sub1, requestZip(admin, projectId, subset),
                "repeat subset-request resumes the live job");
        assertNotEquals(all1, sub1, "different selections are different jobs");
    }

    @Test
    void expiredJobFlipsOnReadAndRebuilds() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Expiry");
        insertMedia(projectId, "originals/1/a.jpg", "uploaded", 1);
        long jobId = insertZip(projectId, "ready",
                "now() - interval '8 days', now() - interval '8 days', " +
                        "now() - interval '1 day'");

        JsonNode polled = poll(admin, "/projects/" + projectId + "/zip/" + jobId);
        assertEquals("expired", polled.get("status").asText(), "past-expiry flips on read");

        long rebuilt = requestZip(admin, projectId, "{}");
        assertNotEquals(jobId, rebuilt, "expired job rebuilds as a new job");
        assertEquals("pending", poll(admin, "/projects/" + projectId + "/zip/" + rebuilt)
                .get("status").asText());
    }

    @Test
    void clientMemberCanRequestButStrangerCannot() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Members");
        insertMedia(projectId, "originals/1/a.jpg", "uploaded", 1);

        AccountService.Invite invite = accounts.inviteClient("client@demie.test", "Client");
        accounts.acceptInvite(invite.rawToken(), "client-pass");
        projects.assignMemberByEmail(projectId, "client@demie.test");
        MockHttpSession client = login("client@demie.test", "client-pass");

        // The SecurityConfig carve-out lets non-admin members POST their ZIP.
        MvcResult result = mvc.perform(post("/projects/" + projectId + "/zip").session(client)
                        .contentType(APPLICATION_JSON).content("{}"))
                .andExpect(status().isAccepted())
                .andReturn();
        assertNotNull(json.readTree(result.getResponse().getContentAsString()).get("job_id"));

        AccountService.Invite strangerInvite =
                accounts.inviteClient("stranger@demie.test", "Stranger");
        accounts.acceptInvite(strangerInvite.rawToken(), "stranger-pass");
        MockHttpSession stranger = login("stranger@demie.test", "stranger-pass");
        mvc.perform(post("/projects/" + projectId + "/zip").session(stranger)
                        .contentType(APPLICATION_JSON).content("{}"))
                .andExpect(status().isForbidden());
        mvc.perform(get("/projects/" + projectId + "/zip/1").session(stranger))
                .andExpect(status().isForbidden());
    }

    @Test
    void tokenPathsWorkWithoutLogin() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Token Zip");
        insertMedia(projectId, "originals/1/a.jpg", "uploaded", 1);
        long otherProject = createProject(admin, "Other");
        String token = issueToken(admin, projectId);

        MvcResult result = mvc.perform(post("/p/" + token + "/zip"))
                .andExpect(status().isAccepted())
                .andReturn();
        long jobId = json.readTree(result.getResponse().getContentAsString())
                .get("job_id").asLong();

        JsonNode polled = json.readTree(mvc.perform(get("/p/" + token + "/zip/" + jobId))
                .andExpect(status().isOk())
                .andReturn().getResponse().getContentAsString());
        assertEquals("pending", polled.get("status").asText());

        mvc.perform(post("/p/nope-not-real/zip")).andExpect(status().isNotFound());
        String otherToken = issueToken(admin, otherProject);
        mvc.perform(get("/p/" + otherToken + "/zip/" + jobId))
                .andExpect(status().isNotFound());
    }

    @Test
    void zipDownloadRedirectsAndRecordsEvent() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set (signing only)");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Zip Download");
        insertMedia(projectId, "originals/1/a.jpg", "uploaded", 1);
        long secondMedia = insertMedia(projectId, "originals/1/b.jpg", "uploaded", 2);
        String token = issueToken(admin, projectId);

        long readyId = insertZip(projectId, "ready",
                "now(), now(), now() + interval '7 days'");

        MvcResult download = mvc.perform(
                        get("/projects/" + projectId + "/zip/" + readyId + "/download").session(admin))
                .andExpect(status().isFound())
                .andReturn();
        String location = download.getResponse().getHeader("Location");
        assertNotNull(location, "ZIP download must 302 to a presigned URL");
        assertTrue(location.contains("zips%2F") || location.contains("zips/"),
                "redirect targets the built object, got: " + location);
        assertTrue(location.contains("response-content-disposition"),
                "redirect forces save-as, got: " + location);

        Map<String, Object> event = jdbc.queryForMap(
                "SELECT account_id, token_id, media_id FROM download_event " +
                        "WHERE project_id = ? AND event_type = 'download'", projectId);
        assertNotNull(event.get("account_id"), "ZIP download links the account");
        assertTrue(event.get("media_id") == null, "ZIP download has no single media");

        MvcResult tokenDownload = mvc.perform(
                        get("/p/" + token + "/zip/" + readyId + "/download"))
                .andExpect(status().isFound())
                .andReturn();
        assertNotNull(tokenDownload.getResponse().getHeader("Location"));

        long pendingId = requestZip(admin, projectId,
                json.writeValueAsString(Map.of("media_ids", new long[]{secondMedia})));
        mvc.perform(get("/projects/" + projectId + "/zip/" + pendingId + "/download")
                        .session(admin))
                .andExpect(status().isConflict());
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

    private long insertMedia(long projectId, String key, String status, int sort) {
        return jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, " +
                        "derivative_status) " +
                        "VALUES (?, ?, 1024, 'photo', ?, ?, 'pending') RETURNING id",
                Long.class, projectId, key, sort, status);
    }

    private long insertZip(long projectId, String status, String timestamps) {
        return jdbc.queryForObject(
                "INSERT INTO zip_job (project_id, status, media_ids, s3_key, size_bytes, " +
                        "started_at, ready_at, expires_at) VALUES (?, ?, '{1}', 'zips/seed.zip', " +
                        "99, " + timestamps + ") RETURNING id",
                Long.class, projectId, status);
    }

    private long requestZip(MockHttpSession session, long projectId, String body)
            throws Exception {
        var request = post("/projects/" + projectId + "/zip").session(session);
        if (body != null) {
            request.contentType(APPLICATION_JSON).content(body);
        }
        MvcResult result = mvc.perform(request)
                .andExpect(status().isAccepted())
                .andReturn();
        JsonNode node = json.readTree(result.getResponse().getContentAsString());
        assertEquals("pending", node.get("status").asText());
        return node.get("job_id").asLong();
    }

    private JsonNode poll(MockHttpSession session, String path) throws Exception {
        MvcResult result = mvc.perform(get(path).session(session))
                .andExpect(status().isOk())
                .andReturn();
        return json.readTree(result.getResponse().getContentAsString());
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
