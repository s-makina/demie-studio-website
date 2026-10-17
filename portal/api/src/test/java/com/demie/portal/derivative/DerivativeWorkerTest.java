package com.demie.portal.derivative;

import com.demie.portal.account.Account;
import com.demie.portal.account.AccountService;
import com.demie.portal.s3.S3Service;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.context.TestPropertySource;

import javax.imageio.ImageIO;
import java.awt.Color;
import java.awt.Graphics2D;
import java.awt.image.BufferedImage;
import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.List;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

/**
 * Task 7 verify: photo derivatives, video poster, poison quarantine and
 * bounded drain. S3-touching tests run against live MinIO and skip without
 * {@code S3_ENDPOINT}/{@code S3_ACCESS_KEY}/{@code S3_SECRET_KEY}; the video
 * test additionally needs an ffmpeg binary. The periodic scheduler is off —
 * tests drive the worker directly.
 */
@SpringBootTest
@ActiveProfiles("test")
@TestPropertySource(properties = "derivative.worker.enabled=false")
class DerivativeWorkerTest {

    @Autowired
    private JdbcTemplate jdbc;

    @Autowired
    private AccountService accounts;

    @Autowired
    private S3Service s3;

    @Autowired
    private DerivativeWorker worker;

    private long projectId;

    @BeforeEach
    void reset() {
        jdbc.execute("TRUNCATE account, project, project_member, media, share_token, " +
                "download_event, zip_job RESTART IDENTITY CASCADE");
        Account admin = accounts.createAdmin("photographer@demie.test", "s3cret-pass", "Demie");
        projectId = jdbc.queryForObject("INSERT INTO project (account_id, name) VALUES (?, ?) RETURNING id",
                Long.class, admin.id(), "Derivatives");
    }

    private static boolean s3Available() {
        return envPresent("S3_ENDPOINT") && envPresent("S3_ACCESS_KEY") && envPresent("S3_SECRET_KEY");
    }

    private static boolean envPresent(String name) {
        String value = System.getenv(name);
        return value != null && !value.isBlank();
    }

    private static boolean ffmpegAvailable() {
        try {
            Process process = new ProcessBuilder("ffmpeg", "-version")
                    .redirectErrorStream(true).start();
            boolean finished = process.waitFor(10, java.util.concurrent.TimeUnit.SECONDS);
            return finished && process.exitValue() == 0;
        } catch (Exception e) {
            return false;
        }
    }

    // ------------------------------------------------------------- pure unit

    @Test
    void derivativeKeysFollowPrefixConvention() {
        var row = new com.demie.portal.media.Media(7L, 42L, "originals/42/7/a.png",
                100L, "photo", 1, "uploaded", null, "pending", 0, 400, 300,
                java.time.Instant.now());
        assertEquals("previews/42/7.jpg", DerivativeWorker.previewKeyFor(row));
        assertEquals("thumbs/42/7.jpg", DerivativeWorker.thumbKeyFor(row));
    }

    // ------------------------------------------------------------- live MinIO

    @Test
    void photoProducesPreviewAndThumb() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(), "live MinIO required");
        byte[] png = testPng(800, 600);
        long mediaId = insertUploadedMedia("originals/" + projectId + "/1/src.png", png, "photo");

        assertEquals(1, worker.processPendingBatch(10));

        assertEquals("ready", derivativeStatus(mediaId));
        BufferedImage preview = readImage(s3.previewsBucket(), previewKey(mediaId));
        assertTrue(preview.getWidth() <= 1600 && preview.getHeight() <= 1600,
                "preview fits 1600px box");
        BufferedImage thumb = readImage(s3.thumbsBucket(), thumbKey(mediaId));
        assertTrue(thumb.getWidth() <= 400 && thumb.getHeight() <= 400,
                "thumb fits 400px box");
    }

    @Test
    void videoProducesPosterPreviewAndThumb() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(), "live MinIO required");
        org.junit.jupiter.api.Assumptions.assumeTrue(ffmpegAvailable(), "ffmpeg required");
        byte[] mp4 = testMp4();
        long mediaId = insertUploadedMedia("originals/" + projectId + "/2/clip.mp4", mp4, "video");

        assertEquals(1, worker.processPendingBatch(10));

        assertEquals("ready", derivativeStatus(mediaId));
        BufferedImage preview = readImage(s3.previewsBucket(), previewKey(mediaId));
        assertNotNull(preview, "video poster preview is a readable image");
        BufferedImage thumb = readImage(s3.thumbsBucket(), thumbKey(mediaId));
        assertTrue(thumb.getWidth() <= 400 && thumb.getHeight() <= 400,
                "video poster thumb fits 400px box");
    }

    @Test
    void poisonQuarantinesAfterRetries() {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(), "live MinIO required");
        byte[] garbage = "this is not an image".getBytes(StandardCharsets.UTF_8);
        long mediaId = insertUploadedMedia("originals/" + projectId + "/3/bogus.png", garbage, "photo");
        putBytes(s3.originalsBucket(), "originals/" + projectId + "/3/bogus.png", garbage, "image/png");

        for (int i = 0; i < 5 && !"failed".equals(derivativeStatus(mediaId)); i++) {
            worker.processPendingBatch(10);
        }

        assertEquals("failed", derivativeStatus(mediaId), "poison row quarantines");
        Integer attempts = jdbc.queryForObject(
                "SELECT derivative_attempts FROM media WHERE id = ?", Integer.class, mediaId);
        assertTrue(attempts >= 3, "poison retried before quarantine, got " + attempts);
        String error = jdbc.queryForObject(
                "SELECT derivative_error FROM media WHERE id = ?", String.class, mediaId);
        assertNotNull(error, "quarantine records the cause");
    }

    @Test
    void boundedBatchDrainsBacklog() throws Exception {
        org.junit.jupiter.api.Assumptions.assumeTrue(s3Available(), "live MinIO required");
        byte[] png = testPng(64, 64);
        int rows = 30;
        for (int i = 0; i < rows; i++) {
            insertUploadedMedia("originals/" + projectId + "/drain/" + i + ".png", png, "photo");
        }

        int settled = worker.processPendingBatch(100);

        assertEquals(rows, settled, "whole backlog claimed in one bounded batch");
        Integer ready = jdbc.queryForObject(
                "SELECT count(*) FROM media WHERE project_id = ? AND derivative_status = 'ready'",
                Integer.class, projectId);
        assertEquals(rows, ready, "backlog drains without leftover pendings");
    }

    // ------------------------------------------------------------- helpers

    private long insertUploadedMedia(String key, byte[] bytes, String kind) {
        String contentType = "photo".equals(kind) ? "image/png" : "video/mp4";
        putBytes(s3.originalsBucket(), key, bytes, contentType);
        return jdbc.queryForObject(
                "INSERT INTO media (project_id, s3_key, size_bytes, kind, sort, status) " +
                        "VALUES (?, ?, ?, ?, 1, 'uploaded') RETURNING id",
                Long.class, projectId, key, (long) bytes.length, kind);
    }

    private void putBytes(String bucket, String key, byte[] bytes, String contentType) {
        s3.putObject(bucket, key, contentType, bytes.length,
                new ByteArrayInputStream(bytes));
    }

    private String derivativeStatus(long mediaId) {
        return jdbc.queryForObject("SELECT derivative_status FROM media WHERE id = ?",
                String.class, mediaId);
    }

    private String previewKey(long mediaId) {
        return "previews/" + projectId + "/" + mediaId + ".jpg";
    }

    private String thumbKey(long mediaId) {
        return "thumbs/" + projectId + "/" + mediaId + ".jpg";
    }

    private BufferedImage readImage(String bucket, String key) throws Exception {
        try (InputStream in = s3.getObject(bucket, key)) {
            return ImageIO.read(in);
        }
    }

    private static byte[] testPng(int width, int height) throws Exception {
        BufferedImage image = new BufferedImage(width, height, BufferedImage.TYPE_INT_RGB);
        Graphics2D graphics = image.createGraphics();
        graphics.setColor(Color.BLUE);
        graphics.fillRect(0, 0, width, height);
        graphics.setColor(Color.YELLOW);
        graphics.fillOval(width / 4, height / 4, width / 2, height / 2);
        graphics.dispose();
        ByteArrayOutputStream out = new ByteArrayOutputStream();
        ImageIO.write(image, "png", out);
        return out.toByteArray();
    }

    private static byte[] testMp4() throws Exception {
        Path dir = Files.createTempDirectory("mp4gen");
        Path clip = dir.resolve("clip.mp4");
        try {
            // Prefer libx264, fall back to mpeg4 (both common in distro ffmpeg).
            boolean made = runFfmpegGen(clip, "libx264") || runFfmpegGen(clip, "mpeg4");
            if (!made) {
                throw new IllegalStateException("no working mp4 encoder");
            }
            return Files.readAllBytes(clip);
        } finally {
            Files.deleteIfExists(clip);
            Files.deleteIfExists(dir);
        }
    }

    private static boolean runFfmpegGen(Path clip, String codec) {
        try {
            Process process = new ProcessBuilder("ffmpeg", "-y",
                    "-f", "lavfi", "-i", "testsrc=duration=1:size=320x240:rate=5",
                    "-pix_fmt", "yuv420p", "-c:v", codec, clip.toString())
                    .redirectErrorStream(true).start();
            // Drain output so the child never blocks on a full pipe.
            try (InputStream ignored = process.getInputStream()) {
                ignored.readAllBytes();
            }
            boolean finished = process.waitFor(30, java.util.concurrent.TimeUnit.SECONDS);
            return finished && process.exitValue() == 0 && Files.size(clip) > 0;
        } catch (Exception e) {
            return false;
        }
    }
}
