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

import java.util.Map;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.springframework.http.MediaType.APPLICATION_JSON;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.patch;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class ProjectAccessTest {

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
    void adminCanCreateProject() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);

        mvc.perform(post("/projects")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("name", "Ama Wedding", "clientName", "Ama"))))
                .andExpect(status().isCreated())
                .andExpect(jsonPath("$.name").value("Ama Wedding"))
                .andExpect(jsonPath("$.clientName").value("Ama"))
                .andExpect(jsonPath("$.status").value("active"));
    }

    @Test
    void clientCannotCreateProject() throws Exception {
        MockHttpSession client = activeClientSession("client@example.com");

        MvcResult result = mvc.perform(post("/projects")
                        .session(client)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("name", "Sneaky"))))
                .andReturn();
        assertEquals(403, result.getResponse().getStatus());
    }

    @Test
    void anonymousCannotCreateProject() throws Exception {
        MvcResult result = mvc.perform(post("/projects")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("name", "Sneaky"))))
                .andReturn();
        int s = result.getResponse().getStatus();
        assertTrue(s == 401 || s == 403, "anonymous create must be denied; got " + s);
    }

    @Test
    void adminCanRenameProject() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long id = createProject(admin, "Old Name");

        mvc.perform(patch("/projects/" + id)
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("name", "New Name"))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.name").value("New Name"));

        mvc.perform(get("/projects/" + id).session(admin))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.name").value("New Name"));
    }

    @Test
    void adminCanArchiveProjectAndItHidesFromClients() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long id = createProject(admin, "To Archive");
        MockHttpSession client = activeClientSession("client@example.com");
        assign(admin, id, "client@example.com");

        mvc.perform(patch("/projects/" + id)
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("status", "archived"))))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.status").value("archived"));

        // Admin still sees it (with archived status); client no longer does.
        mvc.perform(get("/projects").session(admin))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$[?(@.id == " + id + " && @.status == 'archived')]").isArray());
        String clientList = mvc.perform(get("/projects").session(client))
                .andExpect(status().isOk())
                .andReturn().getResponse().getContentAsString();
        assertTrue(json.readTree(clientList).isEmpty(),
                "archived project must disappear from the client list");
    }

    @Test
    void patchRejectsUnknownStatus() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long id = createProject(admin, "Status Check");

        mvc.perform(patch("/projects/" + id)
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("status", "deleted"))))
                .andExpect(status().isBadRequest());
    }

    @Test
    void adminCanAssignInvitedEmailAndClientSeesProject() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long id = createProject(admin, "Thandi Wedding");
        MockHttpSession client = activeClientSession("client@example.com");

        assign(admin, id, "client@example.com");

        mvc.perform(get("/projects").session(client))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$[?(@.id == " + id + ")].name").value("Thandi Wedding"));

        mvc.perform(get("/projects/" + id).session(client))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.name").value("Thandi Wedding"));
    }

    @Test
    void strangerSeesNothing() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long id = createProject(admin, "Private Wedding");
        activeClientSession("stranger@example.com");
        MockHttpSession stranger = login("stranger@example.com", "client-pass-123");

        String list = mvc.perform(get("/projects").session(stranger))
                .andExpect(status().isOk())
                .andReturn().getResponse().getContentAsString();
        assertTrue(json.readTree(list).isEmpty(), "stranger list must be empty");

        mvc.perform(get("/projects/" + id).session(stranger))
                .andExpect(status().isForbidden());
    }

    @Test
    void assignUnknownEmailIsNotFound() throws Exception {
        MockHttpSession admin = login(ADMIN_EMAIL, ADMIN_PASSWORD);
        long id = createProject(admin, "Ghost Wedding");

        mvc.perform(post("/projects/" + id + "/members")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", "ghost@example.com"))))
                .andExpect(status().isNotFound());
    }

    @Test
    void anonymousListIsDenied() throws Exception {
        MvcResult result = mvc.perform(get("/projects")).andReturn();
        int s = result.getResponse().getStatus();
        assertTrue(s == 401 || s == 403, "anonymous list must be denied; got " + s);
    }

    // ------------------------------------------------------------- helpers

    private long createProject(MockHttpSession admin, String name) throws Exception {
        MvcResult result = mvc.perform(post("/projects")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("name", name))))
                .andExpect(status().isCreated())
                .andReturn();
        JsonNode node = json.readTree(result.getResponse().getContentAsString());
        return node.get("id").asLong();
    }

    private void assign(MockHttpSession admin, long projectId, String email) throws Exception {
        mvc.perform(post("/projects/" + projectId + "/members")
                        .session(admin)
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("email", email))))
                .andExpect(status().isOk());
    }

    /** Invite + accept flow, then return a logged-in session for the client. */
    private MockHttpSession activeClientSession(String email) throws Exception {
        invite(email);
        mvc.perform(post("/auth/invite/accept")
                        .contentType(APPLICATION_JSON)
                        .content(body(Map.of("token", inviteToken(email), "password", "client-pass-123"))))
                .andExpect(status().isNoContent());
        return login(email, "client-pass-123");
    }

    private void invite(String email) {
        accounts.inviteClient(email, "Client");
    }

    private String inviteToken(String email) {
        // Re-invite refreshes the token; read the stored hash indirectly by
        // issuing once and capturing the raw token from the service.
        return accounts.inviteClient(email, "Client").rawToken();
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
