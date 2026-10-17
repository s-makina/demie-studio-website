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
import java.time.Duration;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

/**
 * Task 6 verify: drives the real browser flow (init → presigned part PUT →
 * complete) against live MinIO for a 50-file fixture, then asserts
 * cursor-paginated listing is stable. Also covers upload idempotency:
 * a retried init/complete under the same client key must converge to the
 * row already reserved/finished instead of duplicating media. S3-touching
 * tests skip without {@code S3_ENDPOINT}/{@code S3_ACCESS_KEY}/{@code S3_SECRET_KEY}.
 */
@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class MediaUploadTest {

    private static final HttpClient HTTP = HttpClient.newHttpClient();
    private static final int FIXTURE_FILES = 50;
    private static final int PAGE_SIZE = 20;

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
    private MediaService mediaService;

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

    // ------------------------------------------------------- validation / auth

    @Test
    void initRejectsNonImageVideoContent() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Validation");

        mvc.perform(post("/projects/" + projectId + "/media/init")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("filename", "doc.pdf",
                                "contentType", "application/pdf", "sizeBytes", 10,
                                "clientUploadId", "c-pdf"))))
                .andExpect(status().isBadRequest());
    }

    @Test
    void strangerCannotInitUpload() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Guarded Upload");
        MockHttpSession stranger = activeClientSession("stranger@example.com");

        mvc.perform(post("/projects/" + projectId + "/media/init")
                        .session(stranger)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("filename", "a.png",
                                "contentType", "image/png", "sizeBytes", 10,
                                "clientUploadId", "c-guard"))))
                .andExpect(status().isForbidden());
    }

    @Test
    void completeRequiresPartETags() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Empty Complete");
        // Reserve a row directly: no S3 involved, validation happens first.
        Long mediaId = jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status) " +
                        "VALUES (?, 'originals/0/0/x', 0, 'photo', 1, 'uploading') RETURNING id",
                Long.class, projectId);

        mvc.perform(post("/media/" + mediaId + "/complete")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("parts", List.of()))))
                .andExpect(status().isBadRequest());
    }

    @Test
    void invalidCursorIsBadRequest() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Cursor Check");

        mvc.perform(get("/projects/" + projectId + "/media")
                        .session(admin)
                        .param("cursor", "!!!not-base64!!!"))
                .andExpect(status().isBadRequest());
    }

    // ------------------------------------------------------- live S3 round-trip

    @Test
    void fiftyFileFixtureRoundTrip() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Fixture Project");

        // Upload 50 files through the exact browser flow, alternating kinds.
        List<Long> uploadedIds = new ArrayList<>();
        for (int i = 0; i < FIXTURE_FILES; i++) {
            String contentType = (i % 2 == 0) ? "image/png" : "video/mp4";
            byte[] payload = ("fixture-" + i + "-" + System.nanoTime())
                    .getBytes(StandardCharsets.UTF_8);
            uploadedIds.add(uploadOne(admin, projectId, "file-" + i, contentType, payload));
        }
        assertEquals(FIXTURE_FILES, uploadedIds.size());

        // Page through the listing: stable cursors, no dupes, no gaps.
        List<Long> listedIds = new ArrayList<>();
        Map<String, String> kindsById = new LinkedHashMap<>();
        String cursor = null;
        int pages = 0;
        do {
            var builder = get("/projects/" + projectId + "/media")
                    .session(admin)
                    .param("limit", String.valueOf(PAGE_SIZE));
            if (cursor != null) {
                builder.param("cursor", cursor);
            }
            MvcResult result = mvc.perform(builder)
                    .andExpect(status().isOk())
                    .andReturn();
            JsonNode page = json.readTree(result.getResponse().getContentAsString());
            for (JsonNode item : page.get("items")) {
                listedIds.add(item.get("id").asLong());
                kindsById.put(String.valueOf(item.get("id").asLong()),
                        item.get("kind").asText());
            }
            String next = page.get("nextCursor").asText();
            cursor = next.isEmpty() ? null : next;
            pages++;
            assertTrue(pages <= 10, "pagination must terminate");
        } while (cursor != null);

        assertEquals(FIXTURE_FILES, listedIds.size(), "all fixture files listed exactly once");
        assertEquals(FIXTURE_FILES, listedIds.stream().distinct().count(), "no duplicates");
        assertEquals(new ArrayList<>(uploadedIds), listedIds, "listing follows upload (sort) order");

        // Kinds alternate photo/video as uploaded.
        for (int i = 0; i < FIXTURE_FILES; i++) {
            String expected = (i % 2 == 0) ? "photo" : "video";
            assertEquals(expected, kindsById.get(String.valueOf(uploadedIds.get(i))),
                    "kind recorded for fixture file " + i);
        }
        assertEquals(3, pages, "50 files at limit 20 take 3 pages");
    }

    @Test
    void abortDropsRowAndUpload() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Abort Project");

        MvcResult init = mvc.perform(post("/projects/" + projectId + "/media/init")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("filename", "doomed.png",
                                "contentType", "image/png", "sizeBytes", 5,
                                "clientUploadId", "c-doomed"))))
                .andExpect(status().isCreated())
                .andReturn();
        long mediaId = json.readTree(init.getResponse().getContentAsString())
                .get("mediaId").asLong();

        mvc.perform(post("/media/" + mediaId + "/abort").session(admin))
                .andExpect(status().isNoContent());

        Integer remaining = jdbc.queryForObject("SELECT count(*) FROM media WHERE id = ?",
                Integer.class, mediaId);
        assertEquals(0, remaining, "aborted row is gone");
    }

    // ------------------------------------------------------------ idempotency

    @Test
    void initIsIdempotentForSameClientKey() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Idempotent Init");

        String initBody = body(Map.of("filename", "dup.png", "contentType", "image/png",
                "sizeBytes", 10, "clientUploadId", "client-dup"));
        MvcResult first = mvc.perform(post("/projects/" + projectId + "/media/init")
                        .session(admin).contentType(APPLICATION_JSON).content(initBody))
                .andExpect(status().isCreated())
                .andReturn();
        MvcResult second = mvc.perform(post("/projects/" + projectId + "/media/init")
                        .session(admin).contentType(APPLICATION_JSON).content(initBody))
                .andExpect(status().isCreated())
                .andReturn();

        JsonNode one = json.readTree(first.getResponse().getContentAsString());
        JsonNode two = json.readTree(second.getResponse().getContentAsString());
        long mediaId = one.get("mediaId").asLong();
        assertEquals(mediaId, two.get("mediaId").asLong(),
                "a retried init must return the already-reserved row, not a new one");
        assertEquals(one.get("uploadId").asText(), two.get("uploadId").asText(),
                "the open multipart upload must be reused for the retry");
        assertEquals(1, jdbc.queryForObject("SELECT count(*) FROM media WHERE project_id = ?",
                        Integer.class, projectId),
                "exactly one media row per client key");
    }

    @Test
    void initAfterCompleteReportsAlreadyUploaded() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Already Done");
        byte[] payload = "already-here".getBytes(StandardCharsets.UTF_8);
        long mediaId = uploadOne(admin, projectId, "done-once", "image/png", payload);

        // Retry that re-sends the same key after the complete landed.
        MvcResult reinit = mvc.perform(post("/projects/" + projectId + "/media/init")
                        .session(admin).contentType(APPLICATION_JSON)
                        .content(body(Map.of("filename", "done-once", "contentType", "image/png",
                                "sizeBytes", payload.length, "clientUploadId", "c-done-once"))))
                .andExpect(status().isCreated())
                .andReturn();
        JsonNode init = json.readTree(reinit.getResponse().getContentAsString());
        assertTrue(init.get("alreadyUploaded").asBoolean(),
                "init after finalize must report alreadyUploaded");
        assertTrue(init.get("uploadId").isNull(), "finalized row has no open upload id");
        assertEquals(mediaId, init.get("mediaId").asLong(), "same item comes back");

        MvcResult listing = mvc.perform(get("/projects/" + projectId + "/media").session(admin))
                .andExpect(status().isOk())
                .andReturn();
        assertEquals(1, json.readTree(listing.getResponse().getContentAsString()).get("total").asInt(),
                "no second row may appear");
    }

    @Test
    void completeIsIdempotentForRetriedResponse() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set");
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Idempotent Complete");

        // Browser flow up to and including the first complete.
        byte[] payload = "twice-complete".getBytes(StandardCharsets.UTF_8);
        MvcResult initResult = mvc.perform(post("/projects/" + projectId + "/media/init")
                        .session(admin).contentType(APPLICATION_JSON)
                        .content(body(Map.of("filename", "same.png", "contentType", "image/png",
                                "sizeBytes", payload.length, "clientUploadId", "c-same"))))
                .andExpect(status().isCreated())
                .andReturn();
        long mediaId = json.readTree(initResult.getResponse().getContentAsString())
                .get("mediaId").asLong();
        MvcResult partResult = mvc.perform(post("/media/" + mediaId + "/parts")
                        .session(admin).contentType(APPLICATION_JSON)
                        .content(body(Map.of("partNumber", 1))))
                .andExpect(status().isOk())
                .andReturn();
        String partUrl = json.readTree(partResult.getResponse().getContentAsString())
                .get("url").asText();
        HttpResponse<Void> put = HTTP.send(
                HttpRequest.newBuilder().uri(URI.create(partUrl))
                        .PUT(HttpRequest.BodyPublishers.ofByteArray(payload)).build(),
                HttpResponse.BodyHandlers.discarding());
        assertEquals(200, put.statusCode());
        String etag = put.headers().firstValue("ETag").orElseThrow();
        String partsBody = body(Map.of("parts",
                List.of(Map.of("partNumber", 1, "etag", etag))));

        MvcResult complete = mvc.perform(post("/media/" + mediaId + "/complete")
                        .session(admin).contentType(APPLICATION_JSON).content(partsBody))
                .andExpect(status().isOk())
                .andReturn();
        JsonNode first = json.readTree(complete.getResponse().getContentAsString());

        // Lost-response retry: the row is finalized now, so this must replay
        // success instead of failing "upload is not open".
        MvcResult retried = mvc.perform(post("/media/" + mediaId + "/complete")
                        .session(admin).contentType(APPLICATION_JSON).content(partsBody))
                .andExpect(status().isOk())
                .andReturn();
        JsonNode second = json.readTree(retried.getResponse().getContentAsString());
        assertEquals("uploaded", second.get("status").asText(), "retried complete still succeeds");
        assertEquals(first.get("id").asLong(), second.get("id").asLong(), "same item replays");
        assertEquals(payload.length, second.get("sizeBytes").asLong());

        assertEquals(1, jdbc.queryForObject("SELECT count(*) FROM media WHERE project_id = ?",
                Integer.class, projectId));
    }

    @Test
    void reaperDropsStaleUploadRows() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long projectId = createProject(admin, "Stale Uploads");

        Long staleId = jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, created_at) " +
                        "VALUES (?, '', 0, 'photo', 1, 'uploading', now() - interval '48 hours') " +
                        "RETURNING id", Long.class, projectId);
        Long freshId = jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status) " +
                        "VALUES (?, '', 0, 'photo', 2, 'uploading') RETURNING id",
                Long.class, projectId);

        int reaped = mediaService.reapStaleUploads(Duration.ofHours(24), 10);
        assertEquals(1, reaped, "only the 48h-old (never-opened) row is reaped");
        Integer staleLeft = jdbc.queryForObject(
                "SELECT count(*) FROM media WHERE id = ?", Integer.class, staleId);
        Integer freshLeft = jdbc.queryForObject(
                "SELECT count(*) FROM media WHERE id = ?", Integer.class, freshId);
        assertEquals(0, staleLeft, "stale stranded row is dropped");
        assertEquals(1, freshLeft, "fresh upload survives the sweep");
    }

    // ------------------------------------------------------------- helpers

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
        JsonNode done = json.readTree(complete.getResponse().getContentAsString());
        assertEquals("uploaded", done.get("status").asText());
        assertEquals(payload.length, done.get("sizeBytes").asLong(),
                "recorded size is the verified S3 size");
        return done.get("id").asLong();
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
