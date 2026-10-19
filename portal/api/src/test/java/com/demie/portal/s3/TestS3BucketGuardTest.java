package com.demie.portal.s3;

import org.junit.jupiter.api.Test;
import org.springframework.boot.env.YamlPropertySourceLoader;
import org.springframework.core.env.PropertySource;
import org.springframework.core.env.StandardEnvironment;
import org.springframework.core.io.ClassPathResource;

import java.util.List;

import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

/**
 * Guards object storage from the test suite — the S3 counterpart of
 * {@link com.demie.portal.TestProfileGuardTest}.
 *
 * <p>That guard exists because a {@code @SpringBootTest} without the test
 * profile once truncated the developer database. The object store had the same
 * hole and no guard: the suite inherited the production bucket name, wrote
 * fixture derivatives under {@code thumbs/1/&lt;mediaId&gt;.jpg} (test ids always
 * restart at 1), and replaced real gallery thumbnails with a blue-and-yellow
 * test image.
 *
 * <p>This test resolves the properties the same way Spring does for the test
 * profile, including {@code S3_BUCKET_*} exported in the environment, so an
 * override that points the suite back at production fails the build instead of
 * damaging the gallery.
 */
class TestS3BucketGuardTest {

    private static final List<String> BUCKET_PROPERTIES = List.of(
            "S3_BUCKET_ORIGINALS", "S3_BUCKET_PREVIEWS",
            "S3_BUCKET_THUMBS", "S3_BUCKET_ZIPS");

    @Test
    void testProfileUsesOnlyIsolatedBuckets() throws Exception {
        StandardEnvironment environment = new StandardEnvironment();
        // Added last so it has the lowest precedence: an exported environment
        // variable still wins, exactly as it would at run time.
        for (PropertySource<?> source : new YamlPropertySourceLoader()
                .load("application-test", new ClassPathResource("application-test.yml"))) {
            environment.getPropertySources().addLast(source);
        }

        for (String property : BUCKET_PROPERTIES) {
            String bucket = environment.getProperty(property);
            assertNotNull(bucket, property + " is not set for the test profile. Tests write and"
                    + " delete objects, so they must use dedicated '-test' buckets."
                    + " See application-test.yml.");
            assertTrue(bucket.endsWith(TestS3Buckets.TEST_BUCKET_SUFFIX),
                    property + " resolves to '" + bucket + "' for the test profile. Tests write"
                            + " and delete objects, and their project ids always restart at 1, so"
                            + " they must not share a bucket with real photos. Use a name ending in"
                            + " '" + TestS3Buckets.TEST_BUCKET_SUFFIX + "'."
                            + " See application-test.yml.");
        }
    }
}
