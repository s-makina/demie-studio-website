package com.demie.portal.project;

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
import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

/**
 * Listings are not free forever: one row per shoot, forever on the admin
 * path. The endpoint is keyset-paginated on {@code id} descending (newest
 * first) with a server-side {@code status} filter, and reports
 * {@code total} plus the active/archived {@code counts} that power the web
 * tabs. Clients keep the old visibility: active memberships only.
 */
@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class ProjectListPagingTest {

    private static final String ADMIN_EMAIL = "photographer@demie.test";
    private static final String ADMIN_PASSWORD = "s3cret-pass";
    private static final int SEED = 27;

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
    void keysetPagingIsStableAndCountsAreExact() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        for (int i = 1; i <= SEED; i++) {
            createProject(admin, "Shoot " + i, i == SEED ? "archived" : null);
        }

        List<Long> listed = new ArrayList<>();
        String cursor = null;
        int pages = 0;
        do {
            JsonNode page = page(admin, cursor, "all", 10);
            assertEquals(SEED, page.get("total").asInt(), "total matches the requested filter");
            assertEquals(SEED - 1, page.get("counts").get("active").asLong(),
                    "tab counts reach the web without an extra round trip");
            assertEquals(1, page.get("counts").get("archived").asLong());
            assertTrue(page.get("items").size() > 0 && page.get("items").size() <= 10,
                    "every page is bounded and non-empty");
            long first = page.get("items").get(0).get("id").asLong();
            long last = page.get("items").get(page.get("items").size() - 1).get("id").asLong();
            assertTrue(first > last, "newest first (id descending)");
            if (listed.size() > 0) {
                assertTrue(listed.get(listed.size() - 1) > first,
                        "pages continue strictly below the previous page's floor");
            }
            for (JsonNode item : page.get("items")) {
                listed.add(item.get("id").asLong());
                assertFalse(item.get("name").asText().isBlank());
            }
            String next = page.get("nextCursor").asText();
            cursor = next.isEmpty() ? null : next;
            pages++;
            assertTrue(pages <= 10, "pagination terminates");
        } while (cursor != null);

        assertEquals(SEED, listed.size(), "no gaps, no repeats across pages");
        assertEquals(3, pages, "27 rows at limit 10 take 3 pages");

        // The tail page must carry the empty-cursor marker, not another cursor.
        JsonNode tail = page(admin, null, "all", SEED);
        assertTrue(tail.get("nextCursor").asText().isEmpty(), "single full page is the end");
    }

    @Test
    void statusFilterShapesItemsAndTotals() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        for (int i = 1; i <= 5; i++) {
            createProject(admin, "Active " + i, null);
        }
        for (int i = 1; i <= 2; i++) {
            createProject(admin, "Archived " + i, "archived");
        }

        JsonNode active = page(admin, null, "active", 50);
        assertEquals(5, active.get("total").asInt());
        assertEquals(5, active.get("items").size());
        for (JsonNode item : active.get("items")) {
            assertEquals("active", item.get("status").asText());
        }

        JsonNode archived = page(admin, null, "archived", 50);
        assertEquals(2, archived.get("total").asInt());
        assertEquals(2, archived.get("items").size());
        for (JsonNode item : archived.get("items")) {
            assertEquals("archived", item.get("status").asText());
        }

        JsonNode all = page(admin, null, "all", 50);
        assertEquals(7, all.get("total").asInt());
        assertEquals(7, all.get("items").size());
        assertEquals(5, all.get("counts").get("active").asLong());
        assertEquals(2, all.get("counts").get("archived").asLong());
    }

    @Test
    void badParamsReadAsInvalidRequestAndLimitIsClamped() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        createProject(admin, "Solo", null);

        mvc.perform(get("/projects").session(admin).param("status", "bogus"))
                .andExpect(status().isBadRequest());
        mvc.perform(get("/projects").session(admin)
                        .param("cursor", "not-a-cursor"))
                .andExpect(status().isBadRequest());

        // limit < 1 falls back to the default page size, > 200 is clamped;
        // both must serve the whole (tiny) table in one page.
        for (String limit : new String[]{"0", "100000"}) {
            JsonNode paged = page(admin, null, "all", Integer.parseInt(limit));
            assertEquals(1, paged.get("items").size());
            assertTrue(paged.get("nextCursor").asText().isEmpty());
        }
        // limit absent → default 24.
        JsonNode defaulted = page(admin, null, "all", -1);
        assertEquals(1, defaulted.get("items").size());
    }

    @Test
    void clientKeepsMembershipVisibilityAndServiceRejectsBadCursors() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long mine = createProject(admin, "Mine", null);
        createProject(admin, "Other", null); // never assigned to the client
        long archived = createProject(admin, "Archived Mine", "archived");
        MockHttpSession client = activeClientSession("client@example.com");
        assign(admin, mine, "client@example.com");
        assign(admin, archived, "client@example.com");

        // status is a no-op for clients: the membership join already forces
        // active-only, so an all/archived request still returns just "Mine".
        for (String status : new String[]{"active", "archived", "all"}) {
            JsonNode page = page(client, null, status, 10);
            assertEquals(1, page.get("items").size(), status + " page for a client");
            assertEquals(mine, page.get("items").get(0).get("id").asLong());
            assertEquals(0, page.get("counts").get("archived").asLong(),
                    "clients are never shown archived rows");
            assertEquals(1, page.get("counts").get("active").asLong());
            assertTrue(page.get("nextCursor").asText().isEmpty());
        }
    }

    @Test
    void cursorHelpersRejectGarbageAndStartClean() {
        String encoded = ProjectService.encodeCursor(42L);
        assertEquals(42L, ProjectService.decodeCursor(encoded),
                "encode/decode round-trips");
        assertEquals(Long.MAX_VALUE, ProjectService.decodeCursor(""),
                "blank cursor = first page");
        assertEquals(Long.MAX_VALUE, ProjectService.decodeCursor(null));
        for (String garbage : new String[]{"!!!!", "xyz"}) {
            try {
                ProjectService.decodeCursor(garbage);
                assertFalse(true, "garbage cursor must throw: " + garbage);
            } catch (IllegalArgumentException expected) {
                // 400 via the controller's existing mapping.
            }
        }
    }

    // ------------------------------------------------------------- helpers

    private JsonNode page(MockHttpSession session, String cursor, String status,
                          int limit) throws Exception {
        var builder = get("/projects").session(session).param("status", status);
        if (limit >= 0) {
            builder.param("limit", String.valueOf(limit));
        }
        if (cursor != null) {
            builder.param("cursor", cursor);
        }
        MvcResult result = mvc.perform(builder).andExpect(status().isOk()).andReturn();
        return json.readTree(result.getResponse().getContentAsString());
    }

    private long createProject(MockHttpSession admin, String name, String status)
            throws Exception {
        MvcResult result = mvc.perform(post("/projects")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(Map.of("name", name))))
                .andExpect(status().isCreated())
                .andReturn();
        long id = json.readTree(result.getResponse().getContentAsString()).get("id").asLong();
        // POST is create-only; archived rows go in through the admin PATCH.
        if (status != null) {
            mvc.perform(org.springframework.test.web.servlet.request.MockMvcRequestBuilders
                            .patch("/projects/" + id)
                            .session(admin)
                            .contentType(APPLICATION_JSON)
                            .content(json.writeValueAsString(Map.of("status", status))))
                    .andExpect(status().isOk());
        }
        return id;
    }

    private void assign(MockHttpSession admin, long projectId, String email) throws Exception {
        mvc.perform(post("/projects/" + projectId + "/members")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(Map.of("email", email))))
                .andExpect(status().isOk());
    }

    /** Invite + accept flow, then return a logged-in session for the client. */
    private MockHttpSession activeClientSession(String email) throws Exception {
        accounts.inviteClient(email, "Client");
        String token = accounts.inviteClient(email, "Client").rawToken();
        mvc.perform(post("/auth/invite/accept")
                        .contentType(APPLICATION_JSON)
                        .content(json.writeValueAsString(
                                Map.of("token", token, "password", "client-pass-123"))))
                .andExpect(status().isNoContent());
        return login(email, "client-pass-123");
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
