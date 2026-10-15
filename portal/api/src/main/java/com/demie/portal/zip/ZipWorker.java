package com.demie.portal.zip;

import com.demie.portal.media.Media;
import com.demie.portal.media.MediaService;
import com.demie.portal.s3.S3Service;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.stereotype.Service;
import software.amazon.awssdk.services.s3.model.CompletedPart;

import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.HashSet;
import java.util.List;
import java.util.Optional;
import java.util.Set;
import java.util.concurrent.ConcurrentHashMap;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.zip.ZipEntry;
import java.util.zip.ZipOutputStream;

/**
 * ZIP worker (Task 12). Pending jobs are claimed with a conditional
 * {@code UPDATE} (concurrent schedulers never double-build) and streamed:
 * originals flow S3 → ZIP → S3 multipart upload through an 8MB buffer, so
 * neither the originals nor the finished archive ever sit on VPS disk or
 * fully in RAM. At most {@link #MAX_CONCURRENT} builds run at once; extra
 * jobs wait in {@code pending}. Finished objects land at
 * {@code zips/&lt;job-id&gt;.zip} with a 7-day expiry; rebuilds mint a new
 * job (and key), the old object ages out via the bucket lifecycle rule.
 */
@Service
public class ZipWorker {

    private static final Logger log = LoggerFactory.getLogger(ZipWorker.class);

    /** Max simultaneous builds (spec: 1–2). */
    static final int MAX_CONCURRENT = 2;
    /** Part buffer: S3 demands ≥5MB for non-final parts. */
    static final int PART_BYTES = 8 * 1024 * 1024;

    private final JdbcTemplate jdbc;
    private final ZipJobService jobs;
    private final MediaService media;
    private final S3Service s3;
    private final ExecutorService pool = Executors.newFixedThreadPool(MAX_CONCURRENT);
    private final Set<Long> inFlight = ConcurrentHashMap.newKeySet();

    public ZipWorker(JdbcTemplate jdbc, ZipJobService jobs, MediaService media, S3Service s3) {
        this.jdbc = jdbc;
        this.jobs = jobs;
        this.media = media;
        this.s3 = s3;
    }

    /**
     * Claims up to {@code MAX_CONCURRENT − in-flight} pending jobs (oldest
     * first) and starts their builds in the pool. Returns the number claimed;
     * anything beyond the cap stays {@code pending} for a later sweep.
     */
    public int buildNext() {
        int free = MAX_CONCURRENT - inFlight.size();
        if (free <= 0) {
            return 0;
        }
        List<Long> candidates = jdbc.queryForList(
                "SELECT id FROM zip_job WHERE status = 'pending' ORDER BY id LIMIT ?",
                Long.class, free);
        int claimed = 0;
        for (Long id : candidates) {
            int updated = jdbc.update(
                    "UPDATE zip_job SET status = 'running', started_at = now() " +
                            "WHERE id = ? AND status = 'pending'",
                    id);
            if (updated == 1 && inFlight.add(id)) {
                pool.submit(() -> build(id));
                claimed++;
            }
        }
        return claimed;
    }

    /** Flips past-expiry {@code ready} jobs to {@code expired}. Returns the count. */
    public int expireSwept() {
        return jdbc.update("UPDATE zip_job SET status = 'expired' " +
                "WHERE status = 'ready' AND expires_at < now()");
    }

    /** Stops the build pool (tests: each method owns its worker, see ZipWorkerTest). */
    public void shutdown() {
        pool.shutdownNow();
    }

    private void build(long jobId) {
        try {
            ZipJob job = jobs.findById(jobId).orElse(null);
            if (job == null) {
                return;
            }
            List<Media> files = resolveFiles(job);
            String key = "zips/" + jobId + ".zip";
            long sizeBytes = streamZip(jobId, key, files);
            jdbc.update("UPDATE zip_job SET status = 'ready', s3_key = ?, size_bytes = ?, " +
                            "ready_at = now(), expires_at = now() + interval '7 days', " +
                            "error_summary = NULL WHERE id = ?",
                    key, sizeBytes, jobId);
            log.info("zip {} ready: {} files, {} bytes", jobId, files.size(), sizeBytes);
        } catch (Exception e) {
            fail(jobId, e);
        } finally {
            inFlight.remove(jobId);
        }
    }

    /**
     * Loads the job's exact selection in display order. Anything missing or
     * not uploaded fails the build loudly — a ZIP must contain exactly the
     * selected files, never a silent subset.
     */
    private List<Media> resolveFiles(ZipJob job) {
        List<Media> files = new ArrayList<>();
        List<Long> missing = new ArrayList<>();
        for (Long mediaId : job.mediaIds()) {
            Optional<Media> row = media.findById(mediaId);
            if (row.isEmpty() || row.get().projectId() != job.projectId()
                    || !"uploaded".equals(row.get().status())) {
                missing.add(mediaId);
            } else {
                files.add(row.get());
            }
        }
        if (!missing.isEmpty()) {
            throw new IllegalStateException("media not available: " + missing);
        }
        return files;
    }

    private long streamZip(long jobId, String key, List<Media> files) throws IOException {
        String uploadId = s3.createMultipartUpload(s3.zipsBucket(), key, "application/zip");
        List<CompletedPart> parts = new ArrayList<>();
        String zipsBucket = s3.zipsBucket();
        PartStream partStream = new PartStream(key, uploadId, parts,
                (partNumber, bytes) -> s3.uploadPart(zipsBucket, key, uploadId, partNumber,
                        bytes));
        try (partStream; ZipOutputStream zip = new ZipOutputStream(partStream)) {
            Set<String> usedNames = new HashSet<>();
            for (Media file : files) {
                zip.putNextEntry(new ZipEntry(uniqueName(file, usedNames)));
                try (InputStream in = s3.getObject(s3.originalsBucket(), file.s3Key())) {
                    in.transferTo(zip);
                }
                zip.closeEntry();
            }
        } catch (Exception e) {
            s3.abortMultipartUpload(s3.zipsBucket(), key, uploadId);
            if (e instanceof IOException io) {
                throw io;
            }
            throw new IOException("zip build failed", e);
        }
        s3.completeMultipartUpload(s3.zipsBucket(), key, uploadId, parts);
        return partStream.bytesWritten();
    }

    private void fail(long jobId, Exception e) {
        String error = e.getMessage() == null ? e.getClass().getSimpleName()
                : e.getMessage().length() > 500 ? e.getMessage().substring(0, 500)
                        : e.getMessage();
        jdbc.update("UPDATE zip_job SET status = 'failed', error_summary = ? WHERE id = ?",
                error, jobId);
        log.warn("zip {} failed: {}", jobId, error);
    }

    private static String uniqueName(Media file, Set<String> usedNames) {
        String key = file.s3Key();
        int slash = key.lastIndexOf('/');
        String base = slash < 0 ? key : key.substring(slash + 1);
        if (base.isBlank()) {
            base = "file-" + file.id();
        }
        String name = base;
        int copy = 1;
        while (!usedNames.add(name)) {
            copy++;
            int dot = base.lastIndexOf('.');
            name = dot < 0 ? base + " (" + copy + ")"
                    : base.substring(0, dot) + " (" + copy + ")" + base.substring(dot);
        }
        return name;
    }

    /**
     * Uploads one buffered part server-side. Swappable in tests so the
     * buffer/part-boundary logic is verifiable without S3.
     */
    @FunctionalInterface
    interface PartUploader {
        String upload(int partNumber, byte[] bytes);
    }

    /**
     * OutputStream that buffers ZIP bytes and uploads full 8MB parts
     * server-side; the tail goes up as the final (possibly small) part. Only
     * one buffer lives in RAM no matter the archive size.
     */
    static class PartStream extends OutputStream {
        private final String key;
        private final String uploadId;
        private final List<CompletedPart> parts;
        private final PartUploader uploader;
        private final byte[] buffer = new byte[PART_BYTES];
        private int buffered;
        private int partNumber;
        private long totalBytes;
        private boolean finished;

        PartStream(String key, String uploadId, List<CompletedPart> parts,
                   PartUploader uploader) {
            this.key = key;
            this.uploadId = uploadId;
            this.parts = parts;
            this.uploader = uploader;
        }

        /** Compressed bytes uploaded so far (complete after {@link #finish}). */
        long bytesWritten() {
            return totalBytes;
        }

        @Override
        public void write(int b) {
            buffer[buffered++] = (byte) b;
            if (buffered == buffer.length) {
                flushBuffer();
            }
        }

        @Override
        public void write(byte[] source, int off, int len) {
            while (len > 0) {
                int room = buffer.length - buffered;
                int take = Math.min(room, len);
                System.arraycopy(source, off, buffer, buffered, take);
                buffered += take;
                off += take;
                len -= take;
                if (buffered == buffer.length) {
                    flushBuffer();
                }
            }
        }

        /**
         * Uploads the tail as the final part. Emits one part even when
         * nothing was written, so an empty selection still yields a valid
         * (empty) archive object.
         */
        void finish() {
            if (finished) {
                return;
            }
            finished = true;
            if (buffered > 0 || parts.isEmpty()) {
                uploadPartNow();
            }
        }

        private void flushBuffer() {
            if (buffered == 0) {
                return;
            }
            uploadPartNow();
        }

        private void uploadPartNow() {
            byte[] part = Arrays.copyOf(buffer, buffered);
            partNumber++;
            String etag = uploader.upload(partNumber, part);
            parts.add(CompletedPart.builder().partNumber(partNumber).eTag(etag).build());
            totalBytes += buffered;
            buffered = 0;
        }

        @Override
        public void flush() {
            // Mid-build flushes must not emit small non-final parts; the
            // buffer drains on full or at finish().
        }

        @Override
        public void close() {
            finish();
        }
    }
}
