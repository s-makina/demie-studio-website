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

import java.util.ArrayList;
import java.util.List;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

/**
 * Task 22 verify: the virtualized gallery needs more than a forward cursor.
 * {@code offset} drives the timeline scrubber, {@code total} sizes it,
 * {@code endCursor} lets a client resume after uploads land at the end, and
 * {@code thumbWidth}/{@code thumbHeight} let the browser pack the masonry
 * before any image decodes.
 *
 * <p>Rows are inserted directly (no S3 round-trip) with
 * {@code derivative_status='pending'}, so listing stays free of presigning.
 */
@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class MediaPagingTest {

    private static final String ADMIN_EMAIL = "photographer@demie.test";
    private static final String ADMIN_PASSWORD = "s3cret-pass";
    private static final int ROWS = 25;

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
    void keysetPagingIsStableAndReportsTotal() throws Exception {
        MockHttpSession admin = login();
        long projectId = createProject(admin);
        List<Long> ids = seed(projectId, ROWS);

        List<Long> listed = new ArrayList<>();
        String cursor = null;
        int pages = 0;
        do {
            JsonNode page = page(admin, projectId, cursor, null, 10);
            assertEquals(ROWS, page.get("total").asInt(), "total size the scrubber");
            assertEquals(400, page.get("items").get(0).get("thumbWidth").asInt(),
                    "thumb dimensions travel with the listing");
            assertEquals(300, page.get("items").get(0).get("thumbHeight").asInt());
            assertFalse(page.get("items").get(0).get("createdAt").asText().isBlank(),
                    "upload instant drives the day timeline");
            for (JsonNode item : page.get("items")) {
                listed.add(item.get("id").asLong());
            }
            assertTrue(page.get("items").size() > 0 && page.get("items").size() <= 10,
                    "every page is bounded and non-empty");
            assertFalse(page.get("endCursor").asText().isEmpty(),
                    "endCursor always resumes past the last row");
            String next = page.get("nextCursor").asText();
            cursor = next.isEmpty() ? null : next;
            pages++;
            assertTrue(pages <= 10, "pagination terminates");
        } while (cursor != null);

        assertEquals(ids, listed, "listing follows sort order with no gaps or dupes");
        assertEquals(3, pages, "25 rows at limit 10 take 3 pages");
    }

    @Test
    void seekJumpsToOffsetAndEndCursorResumesAfterUploads() throws Exception {
        MockHttpSession admin = login();
        long projectId = createProject(admin);
        List<Long> ids = seed(projectId, ROWS);

        JsonNode sought = page(admin, projectId, null, 7, 5);
        assertEquals(ROWS, sought.get("total").asInt());
        List<Long> soughtIds = new ArrayList<>();
        for (JsonNode item : sought.get("items")) {
            soughtIds.add(item.get("id").asLong());
        }
        assertEquals(ids.subList(7, 12), soughtIds, "offset lands exactly on the seek target");

        // Scrolling forward from a seeked window keeps working (keyset cursor).
        JsonNode more = page(admin, projectId, sought.get("nextCursor").asText(), null, 5);
        List<Long> moreIds = new ArrayList<>();
        for (JsonNode item : more.get("items")) {
            moreIds.add(item.get("id").asLong());
        }
        assertEquals(ids.subList(12, 17), moreIds, "forward paging continues from a seek");

        // Walk to the end and keep its endCursor: that is the upload-resume anchor.
        JsonNode tail = page(admin, projectId, null, ROWS - 3, 10);
        assertEquals(3, tail.get("items").size());
        assertTrue(tail.get("nextCursor").asText().isEmpty(), "tail page is the end");
        String resume = tail.get("endCursor").asText();
        assertFalse(resume.isEmpty(), "the end still yields a resume cursor");

        // New uploads append at the end; resuming from endCursor must find them.
        List<Long> added = seedFrom(projectId, ROWS + 1, 3);
        JsonNode resumed = page(admin, projectId, resume, null, 10);
        List<Long> resumedIds = new ArrayList<>();
        for (JsonNode item : resumed.get("items")) {
            resumedIds.add(item.get("id").asLong());
        }
        assertEquals(added, resumedIds, "resume returns only the newly uploaded rows");
        assertEquals(ROWS + 3, resumed.get("total").asInt(), "total tracks new uploads");
    }

    @Test
    void singleItemViewCarriesLayoutMetadata() throws Exception {
        MockHttpSession admin = login();
        long projectId = createProject(admin);
        List<Long> ids = seed(projectId, 3);

        MvcResult result = mvc.perform(get("/media/" + ids.get(1)).session(admin))
                .andExpect(status().isOk())
                .andReturn();
        JsonNode item = json.readTree(result.getResponse().getContentAsString());
        assertEquals(ids.get(1), item.get("id").asLong());
        assertEquals(400, item.get("thumbWidth").asInt());
        assertEquals(300, item.get("thumbHeight").asInt());
        assertFalse(item.get("createdAt").asText().isBlank());
        // Pending derivatives expose no presigned URLs.
        assertTrue(item.get("thumbUrl").isNull());
    }

    // ------------------------------------------------------------- helpers

    private JsonNode page(MockHttpSession admin, long projectId, String cursor,
                          Integer offset, int limit) throws Exception {
        var builder = get("/projects/" + projectId + "/media")
                .session(admin)
                .param("limit", String.valueOf(limit));
        if (cursor != null) {
            builder.param("cursor", cursor);
        }
        if (offset != null) {
            builder.param("offset", String.valueOf(offset));
        }
        MvcResult result = mvc.perform(builder).andExpect(status().isOk()).andReturn();
        return json.readTree(result.getResponse().getContentAsString());
    }

    private List<Long> seed(long projectId, int rows) {
        return seedFrom(projectId, 1, rows);
    }

    private List<Long> seedFrom(long projectId, int firstSort, int rows) {
        List<Long> ids = new ArrayList<>();
        for (int sort = firstSort; sort < firstSort + rows; sort++) {
            ids.add(jdbc.queryForObject(
                    "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, " +
                            "derivative_status, thumb_width, thumb_height) " +
                            "VALUES (?, ?, 10, 'photo', ?, 'uploaded', 'pending', 400, 300) " +
                            "RETURNING id",
                    Long.class, projectId, "originals/" + projectId + "/" + sort, sort));
        }
        return ids;
    }

    private long createProject(MockHttpSession admin) throws Exception {
        MvcResult result = mvc.perform(post("/projects")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(java.util.Map.of("name", "Seek Project"))))
                .andExpect(status().isCreated())
                .andReturn();
        return json.readTree(result.getResponse().getContentAsString()).get("id").asLong();
    }

    private MockHttpSession login() throws Exception {
        MvcResult result = mvc.perform(post("/auth/login")
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(
                                java.util.Map.of("email", ADMIN_EMAIL, "password", ADMIN_PASSWORD))))
                .andReturn();
        assertEquals(200, result.getResponse().getStatus(), "login should succeed for test setup");
        return (MockHttpSession) result.getRequest().getSession(false);
    }
}
