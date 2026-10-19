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
 * Test-only counterpart to {@link S3BucketBootstrap}: requires an isolated
 * bucket, then makes sure it exists.
 *
 * <p>{@code S3BucketBootstrap} is excluded from the {@code test} profile, so
 * nothing used to create the buckets the suite writes to. Worse, nothing
 * stopped the suite from writing to the <em>live</em> bucket: {@link S3Service}
 * falls back to {@code demie-studio} when the {@code S3_BUCKET_*} properties are
 * missing, and because every integration test truncates with
 * {@code RESTART IDENTITY} the test project id is always 1 — so fixture
 * derivatives landed on the real {@code thumbs/1/&lt;mediaId&gt;.jpg} keys. That
 * is how a test fixture became the gallery thumbnail for real photos.
 *
 * <p>Refusing a non-test bucket turns that silent corruption into a failing
 * context. When S3 is not configured at all the suite runs without object
 * storage, exactly as before.
 */
@Component
@Profile("test")
public class TestS3Buckets implements ApplicationRunner {

    /** Every test bucket must carry this suffix; see application-test.yml. */
    static final String TEST_BUCKET_SUFFIX = "-test";

    private static final Logger log = LoggerFactory.getLogger(TestS3Buckets.class);

    private final S3Service s3;
    private final String endpoint;
    private final String accessKey;
    private final String secretKey;

    public TestS3Buckets(S3Service s3,
                         @Value("${S3_ENDPOINT:}") String endpoint,
                         @Value("${S3_ACCESS_KEY:}") String accessKey,
                         @Value("${S3_SECRET_KEY:}") String secretKey) {
        this.s3 = s3;
        this.endpoint = endpoint;
        this.accessKey = accessKey;
        this.secretKey = secretKey;
    }

    /**
     * True when every configured bucket is an isolated test bucket. Callers
     * that write objects should gate on this, not just on the S3 environment
     * being present.
     */
    public static boolean isolated(S3Service s3) {
        return java.util.stream.Stream.of(
                        s3.originalsBucket(), s3.previewsBucket(), s3.thumbsBucket(), s3.zipsBucket())
                .allMatch(bucket -> bucket.endsWith(TEST_BUCKET_SUFFIX));
    }

    @Override
    public void run(ApplicationArguments args) {
        if (!configured()) {
            log.info("S3 not configured for tests; object-storage tests will skip");
            return;
        }

        Set<String> buckets = new LinkedHashSet<>();
        buckets.add(s3.originalsBucket());
        buckets.add(s3.previewsBucket());
        buckets.add(s3.thumbsBucket());
        buckets.add(s3.zipsBucket());

        for (String bucket : buckets) {
            if (!bucket.endsWith(TEST_BUCKET_SUFFIX)) {
                throw new IllegalStateException("The test profile points at bucket '"
                        + bucket + "', which is not an isolated test bucket (expected a name"
                        + " ending in '" + TEST_BUCKET_SUFFIX + "'). Refusing to run: the suite"
                        + " writes and deletes objects, and its media ids restart at 1, so it would"
                        + " overwrite the real thumbnails and previews of project 1. Set the"
                        + " S3_BUCKET_* properties to test buckets in application-test.yml.");
            }
        }

        for (String bucket : buckets) {
            s3.ensureBucket(bucket);
        }
        log.info("Test object storage ready at {} (buckets: {})", endpoint, buckets);
    }

    private boolean configured() {
        return present(endpoint) && present(accessKey) && present(secretKey);
    }

    private static boolean present(String value) {
        return value != null && !value.isBlank();
    }
}
