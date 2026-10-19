package com.demie.portal.derivative;

import org.junit.jupiter.api.Test;
import software.amazon.awssdk.auth.credentials.AwsBasicCredentials;
import software.amazon.awssdk.auth.credentials.StaticCredentialsProvider;
import software.amazon.awssdk.core.ResponseInputStream;
import software.amazon.awssdk.regions.Region;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.GetObjectRequest;
import software.amazon.awssdk.services.s3.model.GetObjectResponse;
import software.amazon.awssdk.services.s3.model.NoSuchKeyException;
import software.amazon.awssdk.services.s3.model.S3Exception;

import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import java.io.ByteArrayInputStream;
import java.net.URI;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.ResultSet;
import java.sql.Statement;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.TreeMap;

/**
 * THROWAWAY verification probe (delete once the repair is confirmed). Two jobs:
 *
 * <ul>
 *   <li>{@link #report()} — read-only snapshot of every bucket prefix and of
 *   each real media row's stored thumb/preview, including a colour comparison
 *   against the original.</li>
 *   <li>{@link #resetForRegeneration()} — re-queues the real media rows so the
 *   running derivative worker rebuilds thumbs and previews from the intact
 *   originals. Requires {@code -Drepair=confirm}.</li>
 * </ul>
 *
 * Credentials come from portal/infra/.env at run time.
 */
class DerivativeVerificationProbe {

    @Test
    void report() throws Exception {
        Env env = new Env();
        S3Client s3 = env.s3();
        try {
            System.out.println("[VERIFY] buckets:");
            s3.listBuckets().buckets().forEach(b ->
                    System.out.println("[VERIFY]   " + b.name()));

            for (var bucket : s3.listBuckets().buckets()) {
                Map<String, Integer> counts = new TreeMap<>();
                s3.listObjectsV2Paginator(x -> x.bucket(bucket.name())).contents().forEach(o -> {
                    String prefix = o.key().contains("/") ? o.key().substring(0, o.key().indexOf('/'))
                            : "(root)";
                    counts.merge(prefix, 1, Integer::sum);
                });
                System.out.println("[VERIFY] " + bucket.name() + " prefixes: " + counts);
            }

            // Where did the derivative test's fixture original actually land?
            for (String bucketName : new String[]{env.bucket(), env.bucket() + "-test"}) {
                System.out.println("[VERIFY] keys in " + bucketName + ":");
                s3.listObjectsV2Paginator(x -> x.bucket(bucketName)).contents().forEach(o ->
                        System.out.println("[VERIFY]   " + o.key() + " " + o.size() + "b "
                                + o.lastModified()));
                for (String key : new String[]{"originals/1/1/src.png", "originals/1/2/clip.mp4"}) {
                    try {
                        var head = s3.headObject(b -> b.bucket(bucketName).key(key));
                        System.out.println("[VERIFY] head " + bucketName + "/" + key + " -> "
                                + head.contentLength() + "b modified " + head.lastModified());
                    } catch (Exception e) {
                        System.out.println("[VERIFY] head " + bucketName + "/" + key + " -> "
                                + e.getClass().getSimpleName());
                    }
                }
            }

            try (Connection db = env.db()) {
                List<Object[]> rows = new ArrayList<>();
                try (Statement st = db.createStatement();
                     ResultSet rs = st.executeQuery(
                             "SELECT id, project_id, s3_key, derivative_status, thumb_width, thumb_height "
                                     + "FROM media ORDER BY project_id, id")) {
                    while (rs.next()) {
                        rows.add(new Object[]{
                                rs.getLong("id"), rs.getLong("project_id"), rs.getString("s3_key"),
                                rs.getString("derivative_status"), rs.getObject("thumb_width"),
                                rs.getObject("thumb_height")});
                    }
                }
                for (Object[] row : rows) {
                    long id = (Long) row[0];
                    long projectId = (Long) row[1];
                    String s3Key = (String) row[2];
                    String thumbKey = "thumbs/" + projectId + "/" + id + ".jpg";
                    String previewKey = "previews/" + projectId + "/" + id + ".jpg";
                    String thumbState = describeStored(s3, env.bucket(), thumbKey);
                    String previewState = describeStored(s3, env.bucket(), previewKey);
                    System.out.printf("[VERIFY] media %d p%d status=%s dims=%sx%s thumb=%s preview=%s%n",
                            id, projectId, row[3], row[4], row[5], thumbState, previewState);

                    BufferedImage thumb = fetchImage(s3, env.bucket(), thumbKey);
                    BufferedImage original = fetchImage(s3, env.bucket(), s3Key);
                    if (thumb != null && original != null) {
                        double[] o = mean(original);
                        double[] t = mean(thumb);
                        System.out.printf("[VERIFY]   mean original=(%.0f,%.0f,%.0f) thumb=(%.0f,%.0f,%.0f)%n",
                                o[0], o[1], o[2], t[0], t[1], t[2]);
                    }
                }
            }
        } finally {
            s3.close();
        }
    }

    @Test
    void roundTripOnTestBucket() throws Exception {
        Env env = new Env();
        S3Client s3 = env.s3();
        try {
            String bucket = System.getProperty("rt.bucket", env.bucket() + "-test");
            String key = "probe/" + System.nanoTime() + ".txt";
            byte[] body = "hello".getBytes(java.nio.charset.StandardCharsets.UTF_8);
            var put = s3.putObject(b -> b.bucket(bucket).key(key).contentType("text/plain"),
                    software.amazon.awssdk.core.sync.RequestBody.fromBytes(body));
            System.out.println("[RT] put " + bucket + "/" + key + " (" + body.length
                    + "b) eTag=" + put.eTag() + " status=" + put.sdkHttpResponse().statusCode());
            put.sdkHttpResponse().headers().forEach((name, values) ->
                    System.out.println("[RT] resp " + name + "=" + values));
            Thread.sleep(1500);
            System.out.println("[RT] listing probe/ in " + bucket + ":");
            s3.listObjectsV2Paginator(b -> b.bucket(bucket).prefix("probe/")).contents().forEach(o ->
                    System.out.println("[RT]   found " + o.key() + " " + o.size() + "b"));
            try {
                var head = s3.headObject(b -> b.bucket(bucket).key(key));
                System.out.println("[RT] head -> " + head.contentLength() + "b");
            } catch (Exception e) {
                System.out.println("[RT] head -> " + e.getClass().getSimpleName() + ": " + e.getMessage());
            }
            try (var in = s3.getObject(b -> b.bucket(bucket).key(key))) {
                System.out.println("[RT] get -> " + new String(in.readAllBytes(),
                        java.nio.charset.StandardCharsets.UTF_8));
            } catch (Exception e) {
                System.out.println("[RT] get -> " + e.getClass().getSimpleName());
            }
            s3.deleteObject(b -> b.bucket(bucket).key(key));
            System.out.println("[RT] deleted");
        } finally {
            s3.close();
        }
    }

    @Test
    void roundTripWithExpectDisabled() throws Exception {
        Env env = new Env();
        S3Client s3 = S3Client.builder()
                .region(Region.US_EAST_1)
                .endpointOverride(URI.create(env.values.get("S3_ENDPOINT")))
                .credentialsProvider(StaticCredentialsProvider.create(AwsBasicCredentials.create(
                        env.values.get("S3_ACCESS_KEY"), env.values.get("S3_SECRET_KEY"))))
                .forcePathStyle(true)
                .httpClientBuilder(software.amazon.awssdk.http.apache.ApacheHttpClient.builder()
                        .expectContinueEnabled(false))
                .build();
        try {
            String bucket = env.bucket() + "-test";
            String key = "probe/no-expect-" + System.nanoTime() + ".txt";
            byte[] body = "hello".getBytes(java.nio.charset.StandardCharsets.UTF_8);
            var put = s3.putObject(b -> b.bucket(bucket).key(key).contentType("text/plain"),
                    software.amazon.awssdk.core.sync.RequestBody.fromBytes(body));
            System.out.println("[RT2] put eTag=" + put.eTag());
            try {
                var head = s3.headObject(b -> b.bucket(bucket).key(key));
                System.out.println("[RT2] head -> " + head.contentLength() + "b");
            } catch (Exception e) {
                System.out.println("[RT2] head -> " + e.getClass().getSimpleName());
            }
            s3.deleteObject(b -> b.bucket(bucket).key(key));
            System.out.println("[RT2] cleaned up");
        } finally {
            s3.close();
        }
    }

    @Test
    void roundTripVariants() throws Exception {
        Env env = new Env();
        String bucket = env.bucket() + "-test";
        String endpoint = env.values.get("S3_ENDPOINT");
        var creds = StaticCredentialsProvider.create(AwsBasicCredentials.create(
                env.values.get("S3_ACCESS_KEY"), env.values.get("S3_SECRET_KEY")));

        // (a) chunked encoding disabled
        try (S3Client s3 = S3Client.builder()
                .region(Region.US_EAST_1)
                .endpointOverride(URI.create(endpoint))
                .credentialsProvider(creds)
                .serviceConfiguration(software.amazon.awssdk.services.s3.S3Configuration.builder()
                        .pathStyleAccessEnabled(true).chunkedEncodingEnabled(false).build())
                .build()) {
            probe(s3, bucket, "(a) chunkedEncoding=false");
        }

        // (b) Netty async client (sync wrapper via join)
        try (var s3 = software.amazon.awssdk.services.s3.S3AsyncClient.builder()
                .region(Region.US_EAST_1)
                .endpointOverride(URI.create(endpoint))
                .credentialsProvider(creds)
                .forcePathStyle(true)
                .httpClientBuilder(software.amazon.awssdk.http.nio.netty.NettyNioAsyncHttpClient.builder())
                .build()) {
            String key = "probe/netty-" + System.nanoTime() + ".txt";
            var put = s3.putObject(b -> b.bucket(bucket).key(key).contentType("text/plain"),
                    software.amazon.awssdk.core.async.AsyncRequestBody.fromBytes("hello".getBytes())).join();
            System.out.println("[V] (b) netty put eTag=" + put.eTag());
            try {
                var head = s3.headObject(b -> b.bucket(bucket).key(key)).join();
                System.out.println("[V] (b) netty head -> " + head.contentLength() + "b");
            } catch (Exception e) {
                System.out.println("[V] (b) netty head -> " + e.getCause());
            }
            s3.deleteObject(b -> b.bucket(bucket).key(key)).join();
        }

        // (c) URL connection client is not on the classpath; skip.
    }
    private void probe(S3Client s3, String bucket, String label) {
        String key = "probe/" + System.nanoTime() + ".txt";
        try {
            var put = s3.putObject(b -> b.bucket(bucket).key(key).contentType("text/plain"),
                    software.amazon.awssdk.core.sync.RequestBody.fromBytes("hello".getBytes()));
            System.out.println("[V] " + label + " put eTag=" + put.eTag());
            try {
                var head = s3.headObject(b -> b.bucket(bucket).key(key));
                System.out.println("[V] " + label + " head -> " + head.contentLength() + "b");
            } catch (Exception e) {
                System.out.println("[V] " + label + " head -> " + e.getClass().getSimpleName());
            }
            s3.deleteObject(b -> b.bucket(bucket).key(key));
        } catch (Exception e) {
            System.out.println("[V] " + label + " FAILED: " + e);
        }
    }

    @Test
    void resetForRegeneration() throws Exception {
        if (!"confirm".equals(System.getProperty("repair"))) {
            System.out.println("[VERIFY] pass -Drepair=confirm to re-queue derivatives");
            return;
        }
        Env env = new Env();
        try (Connection db = env.db();
             Statement st = db.createStatement()) {
            int updated = st.executeUpdate(
                    "UPDATE media SET derivative_status = 'pending', derivative_attempts = 0, "
                            + "derivative_error = NULL, thumb_width = NULL, thumb_height = NULL "
                            + "WHERE status = 'uploaded'");
            System.out.println("[VERIFY] re-queued " + updated + " media rows for derivative regeneration");
            try (ResultSet rs = st.executeQuery(
                    "SELECT id, project_id FROM media WHERE derivative_status = 'pending' ORDER BY id")) {
                StringBuilder ids = new StringBuilder();
                while (rs.next()) {
                    ids.append(rs.getLong("id")).append("(p").append(rs.getLong("project_id")).append(") ");
                }
                System.out.println("[VERIFY] pending now: " + ids);
            }
        }
    }

    // ------------------------------------------------------------------ helpers

    private static String describeStored(S3Client s3, String bucket, String key) {
        try {
            var head = s3.headObject(b -> b.bucket(bucket).key(key));
            return head.contentLength() + "b";
        } catch (NoSuchKeyException e) {
            return "MISSING";
        } catch (S3Exception e) {
            return "HTTP" + e.statusCode();
        }
    }

    private static BufferedImage fetchImage(S3Client s3, String bucket, String key) {
        try (ResponseInputStream<GetObjectResponse> stream = s3.getObject(
                GetObjectRequest.builder().bucket(bucket).key(key).build())) {
            return ImageIO.read(new ByteArrayInputStream(stream.readAllBytes()));
        } catch (Exception e) {
            return null;
        }
    }

    private static double[] mean(BufferedImage image) {
        long r = 0;
        long g = 0;
        long b = 0;
        int n = 0;
        int stepX = Math.max(1, image.getWidth() / 40);
        int stepY = Math.max(1, image.getHeight() / 40);
        for (int x = 0; x < image.getWidth(); x += stepX) {
            for (int y = 0; y < image.getHeight(); y += stepY) {
                int rgb = image.getRGB(x, y);
                r += (rgb >> 16) & 0xFF;
                g += (rgb >> 8) & 0xFF;
                b += rgb & 0xFF;
                n++;
            }
        }
        return new double[]{(double) r / n, (double) g / n, (double) b / n};
    }

    /** portal/infra/.env plus the live S3 client and database. */
    private static final class Env {
        private final Map<String, String> values;

        Env() throws Exception {
            this.values = new HashMap<>();
            Path envFile = Paths.get("../infra/.env");
            for (String line : Files.readAllLines(envFile)) {
                String trimmed = line.trim();
                if (trimmed.isEmpty() || trimmed.startsWith("#") || !trimmed.contains("=")) {
                    continue;
                }
                int eq = trimmed.indexOf('=');
                values.put(trimmed.substring(0, eq).trim(), trimmed.substring(eq + 1).trim());
            }
        }

        S3Client s3() {
            return S3Client.builder()
                    .region(Region.US_EAST_1)
                    .endpointOverride(URI.create(System.getProperty("rt.endpoint", values.get("S3_ENDPOINT"))))
                    .credentialsProvider(StaticCredentialsProvider.create(AwsBasicCredentials.create(
                            values.get("S3_ACCESS_KEY"), values.get("S3_SECRET_KEY"))))
                    .forcePathStyle(true)
                    .build();
        }

        String bucket() {
            return values.getOrDefault("S3_BUCKET_THUMBS", "demie-studio");
        }

        Connection db() throws Exception {
            String url = "jdbc:postgresql://" + values.getOrDefault("DB_HOST", "localhost") + ":"
                    + values.getOrDefault("DB_PORT", "5432") + "/"
                    + values.getOrDefault("DB_NAME", "demie_portal");
            return DriverManager.getConnection(url,
                    values.getOrDefault("DB_USERNAME", "postgres"),
                    values.getOrDefault("DB_PASSWORD", ""));
        }
    }
}
