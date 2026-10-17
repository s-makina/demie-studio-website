package com.demie.portal.s3;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.context.annotation.Profile;
import org.springframework.stereotype.Component;

import java.util.LinkedHashSet;
import java.util.Set;

/**
 * Fails fast when object storage is misconfigured, then makes sure the buckets
 * exist before the first upload.
 *
 * <p>{@link S3Config} silently falls back to anonymous credentials when the
 * endpoint or the keys are blank. A missing configuration therefore reaches
 * real AWS S3 and only surfaces mid-upload as a {@code NoSuchBucketException},
 * nowhere near the cause. Checking here reports the same problem at start-up,
 * and creating the buckets up front keeps the promise made in
 * {@code infra/buckets.md} that they are ready on first use.
 *
 * <p>Left out of the {@code test} profile: the suite runs without S3 on purpose
 * (S3-touching tests skip themselves) and {@code application-test.yml} keeps
 * object storage optional there.
 */
@Component
@Profile("!test")
public class S3BucketBootstrap implements ApplicationRunner {

    /** The placeholder shipped with {@code infra/.env.example}. */
    private static final String PLACEHOLDER = "change-me";

    private static final Logger log = LoggerFactory.getLogger(S3BucketBootstrap.class);

    private final S3Service s3;
    private final String endpoint;
    private final String accessKey;
    private final String secretKey;

    public S3BucketBootstrap(S3Service s3,
                             @Value("${S3_ENDPOINT:}") String endpoint,
                             @Value("${S3_ACCESS_KEY:}") String accessKey,
                             @Value("${S3_SECRET_KEY:}") String secretKey) {
        this.s3 = s3;
        this.endpoint = endpoint;
        this.accessKey = accessKey;
        this.secretKey = secretKey;
    }

    @Override
    public void run(ApplicationArguments args) {
        require("S3_ENDPOINT", endpoint, "http://<minio-host>:9000");
        require("S3_ACCESS_KEY", accessKey, "<minio service-account access key>");
        require("S3_SECRET_KEY", secretKey, "<minio service-account secret key>");

        Set<String> buckets = new LinkedHashSet<>();
        buckets.add(s3.originalsBucket());
        buckets.add(s3.previewsBucket());
        buckets.add(s3.thumbsBucket());
        buckets.add(s3.zipsBucket());

        // Any auth, permission or connectivity failure propagates out of
        // ensureBucket and stops the application: an API that cannot store
        // bytes is of no use, and a clear start-up failure beats a 500 later.
        for (String bucket : buckets) {
            s3.ensureBucket(bucket);
        }
        log.info("Object storage ready at {} (buckets: {})", endpoint, buckets);
    }

    private void require(String name, String value, String example) {
        if (value == null || value.isBlank()) {
            throw new IllegalStateException(name + " is not set, so uploads would be sent "
                    + "anonymously to real AWS S3 and fail with NoSuchBucketException. Set "
                    + name + " in portal/infra/.env (see infra/.env.example, e.g. " + name + "="
                    + example + ") and restart.");
        }
        if (PLACEHOLDER.equals(value.strip())) {
            throw new IllegalStateException(name + " is still the placeholder from "
                    + "infra/.env.example (" + PLACEHOLDER + "); put in the real MinIO "
                    + "service-account value and restart.");
        }
    }
}
