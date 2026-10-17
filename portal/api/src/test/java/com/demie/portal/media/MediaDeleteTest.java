package com.demie.portal.media;

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

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.util.List;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.delete;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

/**
 * Task 21 verify: admin-only {@code DELETE /media/{id}} access matrix.
 * Only the happy-path delete touches S3 (gated); the guard tests use
 * directly-inserted rows so they run without S3 env.
 */
@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class MediaDeleteTest {

    private static final HttpClient HTTP = HttpClient.newHttpClient();

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

    private static boolean s3Available() {
        return envPresent("S3_ENDPOINT") && envPresent("S3_ACCESS_KEY") && envPresent("S3_SECRET_KEY");
    }

    private static boolean envPresent(String name) {
        String value = System.getenv(name);
        return value != null && !value.isBlank();
    }

    @Test
    void adminCanDeleteFinishedPhoto() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Delete Happy Path");
        byte[] payload = ("delete-me-" + System.nanoTime()).getBytes(StandardCharsets.UTF_8);
        long mediaId = uploadOne(admin, projectId, "gone.png", "image/png", payload);

        mvc.perform(delete("/media/" + mediaId).session(admin))
                .andExpect(status().isNoContent());

        Integer remaining = jdbc.queryForObject("SELECT count(*) FROM media WHERE id = ?",
                Integer.class, mediaId);
        assertEquals(0, remaining, "deleted row is gone");
    }

    @Test
    void clientCannotDelete() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Guarded Delete");
        long mediaId = finishedRow(projectId);
        MockHttpSession client = activeClientSession("client@example.com");
        jdbc.update("INSERT INTO project_member (project_id, account_id, role) " +
                "VALUES (?, (SELECT id FROM account WHERE email = ?), 'viewer')",
                projectId, "client@example.com");

        mvc.perform(delete("/media/" + mediaId).session(client))
                .andExpect(status().isForbidden());
    }

    @Test
    void unknownIdIsNotFound() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);

        mvc.perform(delete("/media/987654321").session(admin))
                .andExpect(status().isNotFound());
    }

    @Test
    void uploadingRowIsBlocked() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Still Uploading");
        Long mediaId = jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, s3_upload_id) " +
                        "VALUES (?, 'originals/0/0/open', 0, 'photo', 1, 'uploading', 'open-upload') RETURNING id",
                Long.class, projectId);

        mvc.perform(delete("/media/" + mediaId).session(admin))
                .andExpect(status().isBadRequest());
    }

    // ------------------------------------------------------------- helpers

    /** Finished (non-uploading) row without touching S3. */
    private long finishedRow(long projectId) {
        Long id = jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status) " +
                        "VALUES (?, 'originals/0/0/done', 10, 'photo', 1, 'uploaded') RETURNING id",
                Long.class, projectId);
        return id == null ? 0 : id;
    }

    /** Full browser flow for one file; returns the media id. */
    private long uploadOne(MockHttpSession admin, long projectId, String name,
                           String contentType, byte[] payload) throws Exception {
        MvcResult initResult = mvc.perform(post("/projects/" + projectId + "/media/init")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("filename", name, "contentType", contentType,
                                "sizeBytes", payload.length, "clientUploadId", "c-" + name))))
                .andExpect(status().isCreated())
                .andReturn();
        JsonNode init = json.readTree(initResult.getResponse().getContentAsString());
        long mediaId = init.get("mediaId").asLong();

        MvcResult partResult = mvc.perform(post("/media/" + mediaId + "/parts")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("partNumber", 1))))
                .andExpect(status().isOk())
                .andReturn();
        String partUrl = json.readTree(partResult.getResponse().getContentAsString())
                .get("url").asText();

        HttpResponse<Void> put = HTTP.send(
                HttpRequest.newBuilder().uri(URI.create(partUrl))
                        .PUT(HttpRequest.BodyPublishers.ofByteArray(payload))
                        .build(),
                HttpResponse.BodyHandlers.discarding());
        assertEquals(200, put.statusCode(), "part PUT must succeed");
        String etag = put.headers().firstValue("ETag").orElseThrow();

        MvcResult complete = mvc.perform(post("/media/" + mediaId + "/complete")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("parts",
                                List.of(Map.of("partNumber", 1, "etag", etag))))))
                .andExpect(status().isOk())
                .andReturn();
        return json.readTree(complete.getResponse().getContentAsString()).get("id").asLong();
    }

    private long createProject(MockHttpSession admin, String name) throws Exception {
        MvcResult result = mvc.perform(post("/projects")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("name", name))))
                .andExpect(status().isCreated())
                .andReturn();
        return json.readTree(result.getResponse().getContentAsString()).get("id").asLong();
    }

    private MockHttpSession activeClientSession(String email) throws Exception {
        String token = accounts.inviteClient(email, "Client").rawToken();
        mvc.perform(post("/auth/invite/accept")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("token", token, "password", "client-pass-123"))))
                .andExpect(status().isNoContent());
        return login(email, "client-pass-123");
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
