package com.demie.portal.derivative;

import com.demie.portal.media.Media;
import com.demie.portal.media.MediaService;
import com.demie.portal.s3.S3Service;
import net.coobird.thumbnailator.Thumbnails;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.Future;
import java.util.concurrent.TimeUnit;

/**
 * Derivative worker (Task 7). The {@code media} table is the queue: rows
 * enter {@code derivative_status='pending'} on upload-complete. Each sweep
 * claims a bounded batch (conditional {@code UPDATE} so concurrent workers
 * never double-process), resizes with a bounded pool, and flips rows to
 * {@code ready} — or to {@code failed} with the error after
 * {@code MAX_ATTEMPTS} tries (poison quarantine).
 *
 * <ul>
 *   <li>Photos: preview (fit 1600px) + thumb (fit 400px), JPEG.</li>
 *   <li>Videos: one poster frame via ffmpeg (no transcode), then the same
 *   preview + thumb pair from that frame.</li>
 * </ul>
 *
 * Originals are streamed to temp files (never fully buffered) and cleaned up.
 */
@Service
public class DerivativeWorker {

    private static final Logger log = LoggerFactory.getLogger(DerivativeWorker.class);

    static final int MAX_ATTEMPTS = 3;
    static final int PREVIEW_MAX_PX = 1600;
    static final int THUMB_MAX_PX = 400;
    static final int POOL_SIZE = 2;

    private final JdbcTemplate jdbc;
    private final MediaService media;
    private final S3Service s3;
    private final ExecutorService pool = Executors.newFixedThreadPool(POOL_SIZE);

    public DerivativeWorker(JdbcTemplate jdbc, MediaService media, S3Service s3) {
        this.jdbc = jdbc;
        this.media = media;
        this.s3 = s3;
    }

    /**
     * Claims up to {@code batchSize} pending rows and processes them with the
     * bounded pool. Returns the number of rows settled (ready or failed).
     */
    public int processPendingBatch(int batchSize) {
        List<Long> candidates = jdbc.queryForList(
                "SELECT id FROM media WHERE derivative_status = 'pending' " +
                        "AND derivative_attempts < ? ORDER BY id LIMIT ?",
                Long.class, MAX_ATTEMPTS, Math.max(1, batchSize));

        List<DerivativeJob> claimed = new ArrayList<>();
        for (Long id : candidates) {
            int updated = jdbc.update(
                    "UPDATE media SET derivative_attempts = derivative_attempts + 1 " +
                            "WHERE id = ? AND derivative_status = 'pending' " +
                            "AND derivative_attempts < ?",
                    id, MAX_ATTEMPTS);
            if (updated == 1) {
                int attempt = jdbc.queryForObject(
                        "SELECT derivative_attempts FROM media WHERE id = ?", Integer.class, id);
                claimed.add(new DerivativeJob(id, attempt));
            }
        }

        List<Future<?>> futures = new ArrayList<>();
        for (DerivativeJob job : claimed) {
            futures.add(pool.submit(() -> settle(job)));
        }
        for (Future<?> future : futures) {
            try {
                future.get(10, TimeUnit.MINUTES);
            } catch (Exception e) {
                log.warn("derivative job interrupted/timed out", e);
            }
        }
        return claimed.size();
    }

    private void settle(DerivativeJob job) {
        try {
            Media row = media.findById(job.mediaId()).orElse(null);
            if (row == null || !"uploaded".equals(row.status())) {
                // Row deleted or upload rolled back; drop the claim silently.
                jdbc.update("UPDATE media SET derivative_status = 'ready', " +
                                "derivative_error = NULL WHERE id = ?",
                        job.mediaId());
                return;
            }
            makeDerivatives(row);
            jdbc.update("UPDATE media SET derivative_status = 'ready', " +
                            "derivative_error = NULL WHERE id = ?",
                    job.mediaId());
            log.info("derivatives ready for media {} (attempt {})", job.mediaId(), job.attempt());
        } catch (Exception e) {
            String error = e.getMessage() == null ? e.getClass().getSimpleName()
                    : e.getMessage().length() > 500 ? e.getMessage().substring(0, 500)
                            : e.getMessage();
            if (job.attempt() >= MAX_ATTEMPTS) {
                jdbc.update("UPDATE media SET derivative_status = 'failed', derivative_error = ? " +
                                "WHERE id = ?",
                        error, job.mediaId());
                log.warn("derivative poison quarantined for media {}: {}", job.mediaId(), error);
            } else {
                jdbc.update("UPDATE media SET derivative_error = ? WHERE id = ?",
                        error, job.mediaId());
                log.info("derivative attempt {} failed for media {}, will retry: {}",
                        job.attempt(), job.mediaId(), error);
            }
        }
    }

    private void makeDerivatives(Media row) throws Exception {
        Path workdir = Files.createTempDirectory("deriv-" + row.id());
        try {
            Path original = workdir.resolve("original");
            try (InputStream in = s3.getObject(s3.originalsBucket(), row.s3Key())) {
                Files.copy(in, original);
            }

            Path frame = original;
            if ("video".equals(row.kind())) {
                frame = workdir.resolve("frame.jpg");
                extractPosterFrame(original, frame);
            }

            Path preview = workdir.resolve("preview.jpg");
            Path thumb = workdir.resolve("thumb.jpg");
            resize(frame, preview, PREVIEW_MAX_PX);
            resize(frame, thumb, THUMB_MAX_PX);

            try (InputStream previewIn = Files.newInputStream(preview)) {
                s3.putObject(s3.previewsBucket(), previewKeyFor(row), "image/jpeg",
                        Files.size(preview), previewIn);
            }
            try (InputStream thumbIn = Files.newInputStream(thumb)) {
                s3.putObject(s3.thumbsBucket(), thumbKeyFor(row), "image/jpeg",
                        Files.size(thumb), thumbIn);
            }
        } finally {
            deleteTree(workdir);
        }
    }

    private static void resize(Path source, Path dest, int maxPx) throws IOException {
        try (OutputStream out = Files.newOutputStream(dest)) {
            Thumbnails.of(source.toFile())
                    .size(maxPx, maxPx)
                    .outputFormat("JPEG")
                    .toOutputStream(out);
        }
    }

    private static void extractPosterFrame(Path video, Path dest) throws Exception {
        // Prefer 1s in (skips black leaders); fall back to the first frame for
        // clips shorter than the seek point.
        if (tryExtractFrame(video, dest, "1")) {
            return;
        }
        if (tryExtractFrame(video, dest, "0")) {
            return;
        }
        throw new IOException("ffmpeg could not extract a poster frame");
    }

    private static boolean tryExtractFrame(Path video, Path dest, String seekSeconds) {
        try {
            Files.deleteIfExists(dest);
            Process process = new ProcessBuilder(
                    "ffmpeg", "-y", "-ss", seekSeconds, "-i", video.toString(),
                    "-frames:v", "1", "-q:v", "4", dest.toString())
                    .redirectErrorStream(true)
                    .start();
            boolean finished = process.waitFor(60, TimeUnit.SECONDS);
            if (!finished) {
                process.destroyForcibly();
                return false;
            }
            return process.exitValue() == 0 && Files.exists(dest) && Files.size(dest) > 0;
        } catch (Exception e) {
            return false;
        }
    }

    private static void deleteTree(Path root) {
        try (var paths = Files.walk(root)) {
            paths.sorted(Comparator.reverseOrder()).forEach(path -> {
                try {
                    Files.deleteIfExists(path);
                } catch (IOException ignored) {
                    // Best-effort temp cleanup.
                }
            });
        } catch (IOException ignored) {
            // Best-effort temp cleanup.
        }
    }

    /** Preview key convention: {@code previews/<projectId>/<mediaId>.jpg}. */
    public static String previewKeyFor(Media row) {
        return "previews/" + row.projectId() + "/" + row.id() + ".jpg";
    }

    /** Thumb key convention: {@code thumbs/<projectId>/<mediaId>.jpg}. */
    public static String thumbKeyFor(Media row) {
        return "thumbs/" + row.projectId() + "/" + row.id() + ".jpg";
    }
}
