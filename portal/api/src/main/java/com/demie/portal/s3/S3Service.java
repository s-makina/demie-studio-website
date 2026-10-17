package com.demie.portal.s3;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.AbortMultipartUploadRequest;
import software.amazon.awssdk.services.s3.model.BucketAlreadyExistsException;
import software.amazon.awssdk.services.s3.model.BucketAlreadyOwnedByYouException;
import software.amazon.awssdk.services.s3.model.CompleteMultipartUploadRequest;
import software.amazon.awssdk.services.s3.model.CompletedMultipartUpload;
import software.amazon.awssdk.services.s3.model.CompletedPart;
import software.amazon.awssdk.services.s3.model.CreateBucketRequest;
import software.amazon.awssdk.services.s3.model.CreateMultipartUploadRequest;
import software.amazon.awssdk.services.s3.model.GetObjectRequest;
import software.amazon.awssdk.services.s3.model.HeadBucketRequest;
import software.amazon.awssdk.services.s3.model.HeadObjectRequest;
import software.amazon.awssdk.services.s3.model.HeadObjectResponse;
import software.amazon.awssdk.services.s3.model.NoSuchBucketException;
import software.amazon.awssdk.services.s3.model.NoSuchKeyException;
import software.amazon.awssdk.services.s3.model.PutObjectRequest;
import software.amazon.awssdk.services.s3.model.S3Exception;
import software.amazon.awssdk.services.s3.model.UploadPartRequest;
import software.amazon.awssdk.services.s3.presigner.S3Presigner;
import software.amazon.awssdk.services.s3.presigner.model.GetObjectPresignRequest;
import software.amazon.awssdk.services.s3.presigner.model.PresignedGetObjectRequest;
import software.amazon.awssdk.services.s3.presigner.model.PresignedPutObjectRequest;
import software.amazon.awssdk.services.s3.presigner.model.PresignedUploadPartRequest;
import software.amazon.awssdk.services.s3.presigner.model.PutObjectPresignRequest;
import software.amazon.awssdk.services.s3.presigner.model.UploadPartPresignRequest;

import java.io.InputStream;
import java.time.Duration;
import java.util.List;
import java.util.Map;
import java.util.Optional;

@Service
public class S3Service {

    private final S3Client s3;
    private final S3Presigner presigner;
    private final String originalsBucket;
    private final String previewsBucket;
    private final String thumbsBucket;
    private final String zipsBucket;

    public S3Service(S3Client s3, S3Presigner presigner,
                     // Single-bucket mode: all four classes live in one bucket
                     // (demie-studio) under key prefixes (originals/, previews/,
                     // thumbs/, zips/). Split via env if buckets ever diverge.
                     @Value("${S3_BUCKET_ORIGINALS:demie-studio}") String originalsBucket,
                     @Value("${S3_BUCKET_PREVIEWS:demie-studio}") String previewsBucket,
                     @Value("${S3_BUCKET_THUMBS:demie-studio}") String thumbsBucket,
                     @Value("${S3_BUCKET_ZIPS:demie-studio}") String zipsBucket) {
        this.s3 = s3;
        this.presigner = presigner;
        this.originalsBucket = originalsBucket;
        this.previewsBucket = previewsBucket;
        this.thumbsBucket = thumbsBucket;
        this.zipsBucket = zipsBucket;
    }

    // ---- bucket names ----

    public String originalsBucket() {
        return originalsBucket;
    }

    public String previewsBucket() {
        return previewsBucket;
    }

    public String thumbsBucket() {
        return thumbsBucket;
    }

    public String zipsBucket() {
        return zipsBucket;
    }

    // ---- presigned PUT (browser/direct upload) ----

    /**
     * Returns a presigned PUT URL for {@code key} in {@code bucket}. If
     * {@code contentType} is provided it is signed and the uploader must send
     * that exact Content-Type; if {@code null} any Content-Type is accepted.
     */
    public PresignedPut presignPut(String bucket, String key, String contentType, Duration expires) {
        var builder = PutObjectRequest.builder()
                .bucket(bucket)
                .key(key);

        if (contentType != null && !contentType.isBlank()) {
            builder.contentType(contentType);
        }

        var request = PutObjectPresignRequest.builder()
                .signatureDuration(expires)
                .putObjectRequest(builder.build())
                .build();

        PresignedPutObjectRequest presigned = presigner.presignPutObject(request);

        PresignedPut put = new PresignedPut();
        put.url = presigned.url().toString();
        put.expiresAt = System.currentTimeMillis() + expires.toMillis();
        // For a PUT with content-type signed, the uploader must include it.
        if (contentType != null && !contentType.isBlank()) {
            put.headers = Map.of("Content-Type", contentType);
        } else {
            put.headers = Map.of();
        }
        return put;
    }

    // ---- presigned GET (download / lightbox) ----

    public PresignedGet presignGet(String bucket, String key, Duration expires) {
        return presignGet(bucket, key, expires, null);
    }

    /**
     * Presigned GET, optionally forcing the browser to save the file under
     * {@code downloadFilename} instead of displaying it inline (used by the
     * per-file download redirect).
     */
    public PresignedGet presignGet(String bucket, String key, Duration expires,
                                    String downloadFilename) {
        var objectRequest = GetObjectRequest.builder()
                .bucket(bucket)
                .key(key);
        if (downloadFilename != null && !downloadFilename.isBlank()) {
            String safe = downloadFilename.replace("\"", "");
            objectRequest.responseContentDisposition(
                    "attachment; filename=\"" + safe + "\"");
        }
        var request = GetObjectPresignRequest.builder()
                .signatureDuration(expires)
                .getObjectRequest(objectRequest.build())
                .build();

        PresignedGetObjectRequest presigned = presigner.presignGetObject(request);

        PresignedGet get = new PresignedGet();
        get.url = presigned.url().toString();
        get.expiresAt = System.currentTimeMillis() + expires.toMillis();
        return get;
    }

    // ---- multipart upload (browser sends bytes direct-to-S3; API signs) ----

    /**
     * Starts a multipart upload. Returns the S3 upload id, which the browser
     * passes back when requesting part URLs and completing.
     */
    public String createMultipartUpload(String bucket, String key, String contentType) {
        var builder = CreateMultipartUploadRequest.builder()
                .bucket(bucket)
                .key(key);
        if (contentType != null && !contentType.isBlank()) {
            builder.contentType(contentType);
        }
        return s3.createMultipartUpload(builder.build()).uploadId();
    }

    /** Presigned URL for one part (1-based part number). Bytes never touch the API. */
    public PresignedPart presignUploadPart(String bucket, String key, String uploadId,
                                           int partNumber, Duration expires) {
        var request = UploadPartPresignRequest.builder()
                .signatureDuration(expires)
                .uploadPartRequest(UploadPartRequest.builder()
                        .bucket(bucket)
                        .key(key)
                        .uploadId(uploadId)
                        .partNumber(partNumber)
                        .build())
                .build();

        PresignedUploadPartRequest presigned = presigner.presignUploadPart(request);

        PresignedPart part = new PresignedPart();
        part.url = presigned.url().toString();
        part.expiresAt = System.currentTimeMillis() + expires.toMillis();
        return part;
    }

    /**
     * Completes a multipart upload from the browser-supplied part ETags.
     * This is the one S3 call the API makes itself (metadata only, no bytes).
     */
    public void completeMultipartUpload(String bucket, String key, String uploadId,
                                        List<CompletedPart> parts) {
        s3.completeMultipartUpload(CompleteMultipartUploadRequest.builder()
                .bucket(bucket)
                .key(key)
                .uploadId(uploadId)
                .multipartUpload(CompletedMultipartUpload.builder().parts(parts).build())
                .build());
    }

    /** Best-effort abort of a multipart upload (abandoned or superseded). */
    public void abortMultipartUpload(String bucket, String key, String uploadId) {
        try {
            s3.abortMultipartUpload(AbortMultipartUploadRequest.builder()
                    .bucket(bucket)
                    .key(key)
                    .uploadId(uploadId)
                    .build());
        } catch (Exception e) {
            // Already completed/aborted or never existed; nothing to do.
        }
    }

    /**
     * Server-side upload of one part (workers streaming builds like ZIPs).
     * The byte array must be exactly the part's content; returns its ETag.
     * Non-final parts need ≥5MB — the caller buffers accordingly.
     */
    public String uploadPart(String bucket, String key, String uploadId, int partNumber,
                             byte[] bytes) {
        var response = s3.uploadPart(UploadPartRequest.builder()
                        .bucket(bucket)
                        .key(key)
                        .uploadId(uploadId)
                        .partNumber(partNumber)
                        .contentLength((long) bytes.length)
                        .build(),
                software.amazon.awssdk.core.sync.RequestBody.fromBytes(bytes));
        return response.eTag();
    }

    // ---- server-side object access (workers; never the browser path) ----

    /** Streams an object's bytes (caller must close). Used by workers. */
    public InputStream getObject(String bucket, String key) {
        return s3.getObject(GetObjectRequest.builder().bucket(bucket).key(key).build());
    }

    /** Server-side PUT of worker-produced bytes (derivatives, ZIPs). */
    public void putObject(String bucket, String key, String contentType,
                          long contentLength, InputStream body) {
        s3.putObject(PutObjectRequest.builder()
                        .bucket(bucket)
                        .key(key)
                        .contentType(contentType)
                        .contentLength(contentLength)
                        .build(),
                software.amazon.awssdk.core.sync.RequestBody.fromInputStream(body, contentLength));
    }

    /** Server-side DELETE (test cleanup, project deletion paths). */
    public void deleteObject(String bucket, String key) {
        s3.deleteObject(software.amazon.awssdk.services.s3.model.DeleteObjectRequest.builder()
                .bucket(bucket)
                .key(key)
                .build());
    }

    // ---- object metadata / existence ----

    public Optional<Long> objectSize(String bucket, String key) {
        try {
            HeadObjectResponse head = s3.headObject(HeadObjectRequest.builder()
                    .bucket(bucket)
                    .key(key)
                    .build());
            return Optional.of(head.contentLength());
        } catch (NoSuchKeyException e) {
            return Optional.empty();
        }
    }

    // ---- bucket lifecycle (used by start-up + tests) ----

    /**
     * Creates the bucket when it is missing and does nothing when it already
     * exists.
     *
     * <p>Head-then-create rather than create-and-swallow: swallowing every
     * {@code createBucket} failure hides bad credentials behind a start-up that
     * looks healthy, and the upload only fails much later as a confusing
     * {@code NoSuchBucketException}. Auth, permission and connectivity problems
     * propagate so the caller can fail fast.
     */
    public void ensureBucket(String bucket) {
        if (bucketExists(bucket)) {
            return;
        }
        try {
            s3.createBucket(CreateBucketRequest.builder().bucket(bucket).build());
        } catch (BucketAlreadyExistsException | BucketAlreadyOwnedByYouException e) {
            // Concurrent start-up created it first; it exists, which is all we need.
        }
    }

    private boolean bucketExists(String bucket) {
        try {
            s3.headBucket(HeadBucketRequest.builder().bucket(bucket).build());
            return true;
        } catch (NoSuchBucketException e) {
            return false;
        } catch (S3Exception e) {
            if (e.statusCode() == 404) {
                return false;
            }
            throw e;
        }
    }

    // ---- simple value objects (public fields for test ergonomics) ----

    public static final class PresignedPut {
        public String url;
        public Map<String, String> headers;
        public long expiresAt;
    }

    public static final class PresignedGet {
        public String url;
        public long expiresAt;
    }

    public static final class PresignedPart {
        public String url;
        public long expiresAt;
    }
}
