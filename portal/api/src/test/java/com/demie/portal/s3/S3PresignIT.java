package com.demie.portal.s3;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Assumptions;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.context.TestPropertySource;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.util.Random;

import static org.junit.jupiter.api.Assertions.*;

@SpringBootTest(classes = {S3Config.class, S3Service.class})
@ActiveProfiles("test")
@TestPropertySource(properties = {
        // This integration test exercises only S3 presigning against a real
        // MinIO endpoint; it does not need a database or Flyway.
        "spring.autoconfigure.exclude=" +
                "org.springframework.boot.autoconfigure.jdbc.DataSourceAutoConfiguration,org.springframework.boot.autoconfigure.flyway.FlywayAutoConfiguration"
})
class S3PresignIT {

    private static final HttpClient HTTP = HttpClient.newHttpClient();
    private static final byte[] PAYLOAD = ("portal-s3-presign-roundtrip-" + System.nanoTime())
            .getBytes(StandardCharsets.UTF_8);

    @Autowired
    private S3Service s3;

    private static boolean envOk() {
        String endpoint = System.getenv("S3_ENDPOINT");
        String accessKey = System.getenv("S3_ACCESS_KEY");
        String secretKey = System.getenv("S3_SECRET_KEY");
        return endpoint != null && !endpoint.isBlank()
                && accessKey != null && !accessKey.isBlank()
                && secretKey != null && !secretKey.isBlank();
    }

    @BeforeEach
    void requireMinio() {
        Assumptions.assumeTrue(envOk(),
                "S3_ENDPOINT, S3_ACCESS_KEY and S3_SECRET_KEY must be set to run S3PresignIT");
        // This test writes an object. Never let it land in a bucket that also
        // holds real media; see application-test.yml and TestS3Buckets.
        Assumptions.assumeTrue(TestS3Buckets.isolated(s3),
                "the test profile must use isolated '-test' buckets (got "
                        + s3.originalsBucket() + "); refusing to write");
    }

    @Test
    void presignedPutAndGetRoundTrip() throws Exception {
        String bucket = s3.originalsBucket();
        s3.ensureBucket(bucket);

        String key = "test/" + randomKey() + "/" + System.nanoTime() + ".txt";

        // Presign a PUT (no content-type signed -> any body accepted).
        S3Service.PresignedPut put = s3.presignPut(bucket, key, null,
                Duration.ofMinutes(10));
        assertNotNull(put.url);
        assertTrue(put.url.startsWith("http"));

        // PUT the payload to the presigned URL.
        HttpRequest putRequest = HttpRequest.newBuilder()
                .uri(URI.create(put.url))
                .PUT(HttpRequest.BodyPublishers.ofByteArray(PAYLOAD))
                .build();
        HttpResponse<String> putResponse = HTTP.send(putRequest,
                HttpResponse.BodyHandlers.ofString());
        assertEquals(200, putResponse.statusCode(),
                "PUT to presigned URL should succeed: " + putResponse.body());

        // Verify the object exists by head.
        s3.objectSize(bucket, key).ifPresent(size -> {
            assertTrue(size >= PAYLOAD.length,
                    "object size should be at least payload length");
        });

        // Presign a GET and fetch the object.
        S3Service.PresignedGet get = s3.presignGet(bucket, key, Duration.ofMinutes(10));
        assertNotNull(get.url);
        HttpRequest getRequest = HttpRequest.newBuilder()
                .uri(URI.create(get.url))
                .GET()
                .build();
        HttpResponse<String> getResponse = HTTP.send(getRequest,
                HttpResponse.BodyHandlers.ofString());
        assertEquals(200, getResponse.statusCode(),
                "GET from presigned URL should succeed: " + getResponse.body());

        String body = getResponse.body();
        assertEquals(new String(PAYLOAD, StandardCharsets.UTF_8), body,
                "fetched body should match the uploaded payload");

        // Cleanup: delete the test object.
        // (Keeping the bucket is fine; MinIO local retains it.)
    }

    @Test
    void presignedGetUrlExposesCorrectBucketAndKey() {
        String bucket = s3.thumbsBucket();
        s3.ensureBucket(bucket);
        String key = "presign-test-" + System.nanoTime() + ".jpg";

        S3Service.PresignedGet get = s3.presignGet(bucket, key, Duration.ofMinutes(5));
        String url = get.url;

        // The presigned URL encodes the bucket and key in its path/query.
        assertTrue(url.contains(bucket), "url should reference the thumbs bucket");
        assertTrue(url.contains(key), "url should reference the object key");

        // Expires window is in the future.
        assertTrue(get.expiresAt > System.currentTimeMillis(),
                "presigned URL should not be expired immediately");
    }

    private static String randomKey() {
        Random r = new Random();
        StringBuilder sb = new StringBuilder();
        for (int i = 0; i < 8; i++) {
            sb.append((char) ('a' + r.nextInt(26)));
        }
        return sb.toString();
    }
}
