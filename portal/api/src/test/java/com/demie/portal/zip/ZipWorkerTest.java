package com.demie.portal.zip;

import com.demie.portal.account.Account;
import com.demie.portal.account.AccountService;
import com.demie.portal.project.ProjectService;
import com.demie.portal.s3.S3Service;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.test.context.ActiveProfiles;

import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;
import java.time.Duration;
import java.time.Instant;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Random;
import java.util.zip.ZipInputStream;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.junit.jupiter.api.Assertions.fail;

/**
 * Task 12 verify: the worker streams originals S3 → ZIP → S3 without
 * materialising the archive, caps concurrency (the 3rd job waits in
 * {@code pending}), fails loudly on missing files, and retires expired jobs.
 * Runs against live MinIO with real bytes (incl. a 500-file fixture).
 */
@SpringBootTest
@ActiveProfiles("test")
class ZipWorkerTest {

    @Autowired
    private JdbcTemplate jdbc;

    @Autowired
    private AccountService accounts;

    @Autowired
    private ProjectService projects;

    @Autowired
    private ZipJobService jobs;

    @Autowired
    private ZipWorker worker;

    @Autowired
    private S3Service s3;

    private Account admin;
    private long projectId;

    @BeforeEach
    void reset() {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set (live MinIO)");
        jdbc.execute("TRUNCATE account, project, project_member, media, share_token, " +
                "download_event, zip_job RESTART IDENTITY CASCADE");
        accounts.createAdmin("photographer@demie.test", "s3cret-pass", "Demie");
        admin = accounts.findByEmail("photographer@demie.test").orElseThrow();
        projectId = projects.create(admin.id(), "Zip Build", "Client").id();
    }

    @Test
    void buildStreamsExactFiles() throws Exception {
        Map<String, byte[]> files = new HashMap<>();
        files.put("originals/seed/a/photo.jpg", bytes(1024, 11));
        files.put("originals/seed/b/photo.jpg", bytes(2048, 22));
        files.put("originals/seed/c/notes.bin", bytes(512, 33));
        files.put("originals/seed/d/more.bin", bytes(3072, 44));
        List<Long> ids = insertMediaWithBytes(files);

        ZipJob job = jobs.requestForAccount(admin, projectId, null);
        assertEquals(1, worker.buildNext(), "one pending job claimed");

        ZipJob ready = awaitTerminal(job.id(), Duration.ofSeconds(120));
        assertEquals("ready", ready.status());
        assertEquals("zips/" + job.id() + ".zip", ready.s3Key());
        assertNotNull(ready.sizeBytes());
        assertTrue(ready.sizeBytes() > 0, "compressed size recorded");
        assertTrue(ready.expiresAt() != null && ready.expiresAt().isAfter(
                Instant.now().plus(Duration.ofDays(6))), "7-day expiry set");

        Map<String, byte[]> entries = readZip(ready.s3Key());
        assertEquals(4, entries.size(), "ZIP contains exactly the selection");
        // Duplicate basenames are de-conflicted, contents intact.
        assertTrue(entries.containsKey("photo.jpg") && entries.containsKey("photo (2).jpg"),
                "duplicate basenames de-conflicted, got: " + entries.keySet());
        long total = 0;
        for (Map.Entry<String, byte[]> file : files.entrySet()) {
            total += file.getValue().length;
        }
        long uncompressed = entries.values().stream().mapToLong(b -> b.length).sum();
        assertEquals(total, uncompressed, "ZIP bytes round-trip exactly");
        for (byte[] expected : files.values()) {
            assertTrue(entries.values().stream().anyMatch(b -> java.util.Arrays.equals(b, expected)),
                    "each original's bytes present verbatim");
        }
        assertEquals(ids.size(), entries.size());
    }

    @Test
    void fiveHundredFilesByteComplete() throws Exception {
        Map<String, byte[]> files = new HashMap<>();
        Random names = new Random(7);
        for (int i = 0; i < 500; i++) {
            files.put("originals/bulk/" + (names.nextInt(50)) + "/file-" + i + ".bin",
                    randomBytes(2048, 1000 + i));
        }
        insertMediaWithBytes(files);

        ZipJob job = jobs.requestForAccount(admin, projectId, null);
        worker.buildNext();
        ZipJob ready = awaitTerminal(job.id(), Duration.ofMinutes(4));
        assertEquals("ready", ready.status());

        Map<String, byte[]> entries = readZip(ready.s3Key());
        assertEquals(500, entries.size(), "500-file fixture ZIP byte-completes");
        int checked = 0;
        for (Map.Entry<String, byte[]> file : files.entrySet()) {
            if (checked++ % 50 == 0) {
                String base = file.getKey().substring(file.getKey().lastIndexOf('/') + 1);
                boolean found = entries.entrySet().stream()
                        .anyMatch(e -> e.getKey().startsWith(base.replace(".bin", ""))
                                && java.util.Arrays.equals(e.getValue(), file.getValue()));
                assertTrue(found, "spot-check bytes intact for " + base);
            }
        }
    }

    @Test
    void thirdJobQueuesWhileTwoBuild() throws Exception {
        long m1 = insertOne("originals/q/one.bin", bytes(256, 1), 1);
        long m2 = insertOne("originals/q/two.bin", bytes(256, 2), 2);
        long m3 = insertOne("originals/q/three.bin", bytes(256, 3), 3);
        ZipJob j1 = jobs.requestForAccount(admin, projectId, List.of(m1));
        ZipJob j2 = jobs.requestForAccount(admin, projectId, List.of(m2));
        ZipJob j3 = jobs.requestForAccount(admin, projectId, List.of(m3));

        assertEquals(2, worker.buildNext(), "only two builds start at once");
        assertEquals("pending", jobs.findById(j3.id()).orElseThrow().status(),
                "3rd job queues in pending");
        for (long id : new long[]{j1.id(), j2.id()}) {
            String status = jobs.findById(id).orElseThrow().status();
            assertTrue(status.equals("running") || status.equals("ready"),
                    "claimed jobs build, got: " + status);
        }

        assertEquals("ready", awaitTerminal(j1.id(), Duration.ofSeconds(60)).status());
        assertEquals("ready", awaitTerminal(j2.id(), Duration.ofSeconds(60)).status());
        worker.buildNext();
        assertEquals("ready", awaitTerminal(j3.id(), Duration.ofSeconds(60)).status());
        for (long id : new long[]{j1.id(), j2.id(), j3.id()}) {
            assertTrue(s3.objectSize(s3.zipsBucket(), "zips/" + id + ".zip").isPresent(),
                    "finished object exists for job " + id);
        }
    }

    @Test
    void missingMediaFailsJob() {
        long jobId = jdbc.queryForObject(
                "INSERT INTO zip_job (project_id, status, media_ids) " +
                        "VALUES (?, 'pending', '{99999}') RETURNING id",
                Long.class, projectId);

        worker.buildNext();
        ZipJob failed = awaitTerminal(jobId, Duration.ofSeconds(30));
        assertEquals("failed", failed.status());
        assertNotNull(failed.errorSummary());
        assertTrue(failed.errorSummary().contains("99999"),
                "failure names the missing file, got: " + failed.errorSummary());
    }

    @Test
    void expirySweepMarksReadyPastDue() {
        jdbc.update("INSERT INTO zip_job (project_id, status, media_ids, s3_key, size_bytes, " +
                        "started_at, ready_at, expires_at) VALUES (?, 'ready', '{1}', " +
                        "'zips/old.zip', 10, now() - interval '8 days', " +
                        "now() - interval '8 days', now() - interval '1 day')",
                projectId);

        assertEquals(1, worker.expireSwept());
        assertEquals(0, worker.expireSwept(), "sweep is idempotent");
        List<String> statuses = jdbc.queryForList(
                "SELECT status FROM zip_job WHERE project_id = ?", String.class, projectId);
        assertEquals(List.of("expired"), statuses);
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

    private static byte[] bytes(int length, int seed) {
        byte[] data = new byte[length];
        for (int i = 0; i < length; i++) {
            data[i] = (byte) ((seed + i) & 0xFF);
        }
        return data;
    }

    private static byte[] randomBytes(int length, int seed) {
        byte[] data = new byte[length];
        new Random(seed).nextBytes(data);
        return data;
    }

    private long insertOne(String key, byte[] content, int sort) {
        s3.putObject(s3.originalsBucket(), key, "application/octet-stream",
                content.length, new ByteArrayInputStream(content));
        return jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status, " +
                        "derivative_status) VALUES (?, ?, ?, 'photo', ?, 'uploaded', 'pending') " +
                        "RETURNING id",
                Long.class, projectId, key, (long) content.length, sort);
    }

    private List<Long> insertMediaWithBytes(Map<String, byte[]> files) throws Exception {
        // Fixture PUTs run in parallel: sequential uploads to remote MinIO
        // cost ~1s each, which would make the 500-file fixture unusable.
        java.util.concurrent.ExecutorService setup =
                java.util.concurrent.Executors.newFixedThreadPool(10);
        try {
            List<java.util.concurrent.Future<Long>> futures = new java.util.ArrayList<>();
            int sort = 0;
            for (Map.Entry<String, byte[]> file : files.entrySet()) {
                final int order = ++sort;
                final String key = file.getKey();
                final byte[] content = file.getValue();
                futures.add(setup.submit(() -> insertOne(key, content, order)));
            }
            List<Long> ids = new java.util.ArrayList<>();
            for (java.util.concurrent.Future<Long> future : futures) {
                ids.add(future.get(5, java.util.concurrent.TimeUnit.MINUTES));
            }
            return ids;
        } finally {
            setup.shutdownNow();
        }
    }

    private ZipJob awaitTerminal(long jobId, Duration timeout) {
        Instant deadline = Instant.now().plus(timeout);
        ZipJob last = null;
        while (Instant.now().isBefore(deadline)) {
            last = jobs.findById(jobId).orElse(null);
            if (last == null || "ready".equals(last.status()) || "failed".equals(last.status())) {
                break;
            }
            try {
                Thread.sleep(200);
            } catch (InterruptedException e) {
                Thread.currentThread().interrupt();
                break;
            }
        }
        if (last == null || "pending".equals(last.status()) || "running".equals(last.status())) {
            fail("job " + jobId + " never settled, last status: " +
                    (last == null ? "gone" : last.status()));
        }
        return last;
    }

    private Map<String, byte[]> readZip(String key) throws Exception {
        Map<String, byte[]> entries = new HashMap<>();
        try (ZipInputStream zip = new ZipInputStream(s3.getObject(s3.zipsBucket(), key))) {
            java.util.zip.ZipEntry entry;
            while ((entry = zip.getNextEntry()) != null) {
                ByteArrayOutputStream out = new ByteArrayOutputStream();
                zip.transferTo(out);
                entries.put(entry.getName(), out.toByteArray());
                zip.closeEntry();
            }
        }
        return entries;
    }
}
