package com.demie.portal.s3;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.CreateBucketRequest;
import software.amazon.awssdk.services.s3.model.GetObjectRequest;
import software.amazon.awssdk.services.s3.model.HeadObjectRequest;
import software.amazon.awssdk.services.s3.model.HeadObjectResponse;
import software.amazon.awssdk.services.s3.model.NoSuchKeyException;
import software.amazon.awssdk.services.s3.model.PutObjectRequest;
import software.amazon.awssdk.services.s3.presigner.S3Presigner;
import software.amazon.awssdk.services.s3.presigner.model.GetObjectPresignRequest;
import software.amazon.awssdk.services.s3.presigner.model.PresignedGetObjectRequest;
import software.amazon.awssdk.services.s3.presigner.model.PresignedPutObjectRequest;
import software.amazon.awssdk.services.s3.presigner.model.PutObjectPresignRequest;

import java.time.Duration;
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
        var request = GetObjectPresignRequest.builder()
                .signatureDuration(expires)
                .getObjectRequest(GetObjectRequest.builder()
                        .bucket(bucket)
                        .key(key)
                        .build())
                .build();

        PresignedGetObjectRequest presigned = presigner.presignGetObject(request);

        PresignedGet get = new PresignedGet();
        get.url = presigned.url().toString();
        get.expiresAt = System.currentTimeMillis() + expires.toMillis();
        return get;
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

    // ---- bucket lifecycle (used by tests + setup) ----

    public void ensureBucket(String bucket) {
        try {
            s3.createBucket(CreateBucketRequest.builder().bucket(bucket).build());
        } catch (Exception e) {
            // Bucket may already exist; ignore race.
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
}
