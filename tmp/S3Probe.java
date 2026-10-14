import software.amazon.awssdk.auth.credentials.AwsBasicCredentials;
import software.amazon.awssdk.auth.credentials.StaticCredentialsProvider;
import software.amazon.awssdk.regions.Region;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.HeadBucketRequest;
import software.amazon.awssdk.services.s3.model.ListBucketsRequest;
import software.amazon.awssdk.services.s3.model.S3Exception;
import software.amazon.awssdk.services.s3.model.Bucket;

import javax.net.ssl.*;
import java.net.URI;
import java.security.*;
import java.security.cert.X509Certificate;
import java.util.List;

public class S3Probe {
    public static void main(String[] args) throws Exception {
        String accessKey = System.getenv("S3_ACCESS_KEY");
        String secretKey = System.getenv("S3_SECRET_KEY");
        if (accessKey == null || accessKey.isBlank() || secretKey == null || secretKey.isBlank()) {
            System.out.println("SKIP: S3_ACCESS_KEY/S3_SECRET_KEY not set");
            return;
        }
        AwsCredentialsProvider creds = StaticCredentialsProvider.create(
                AwsBasicCredentials.create(accessKey, secretKey));
        Region region = Region.US_EAST_1;

        List<Endpoint> candidates = List.of(
                new Endpoint("http://salvation-minio-bdd8cc-102-202-45-205.sslip.io", false),
                new Endpoint("http://salvation-minio-bdd8cc-102-202-45-205.sslip.io:9000", false),
                new Endpoint("http://salvation-minio-bdd8cc-102-202-45-205.sslip.io:4569", false),
                new Endpoint("http://salvation-minio-bdd8cc-102-202-45-205.sslip.io/s3", false),
                new Endpoint("http://salvation-minio-bdd8cc-102-202-45-205.sslip.io/api", false),
                new Endpoint("https://salvation-minio-bdd8cc-102-202-45-205.sslip.io", true),
                new Endpoint("https://salvation-minio-bdd8cc-102-202-45-205.sslip.io:9000", true),
                new Endpoint("https://salvation-minio-bdd8cc-102-202-45-205.sslip.io/s3", true),
                new Endpoint("https://salvation-minio-bdd8cc-102-202-45-205.sslip.io/api", true)
        );

        for (Endpoint e : candidates) {
            tryEndpoint(e.url, e.https, creds, region);
        }
    }

    private static void tryEndpoint(String base, boolean https, AwsCredentialsProvider creds, Region region) {
        S3Client client;
        try {
            SSLContext ctx = null;
            if (https) {
                ctx = SSLContext.getInstance("TLS");
                ctx.init(new TrustManager[] { new X509TrustManager() {
                    public void checkClientTrusted(X509Certificate[] chain, String authType) {}
                    public void checkServerTrusted(X509Certificate[] chain, String authType) {}
                    public X509Certificate[] getAcceptedIssuers() { return new X509Certificate[0]; }
                }}, new SecureRandom(), new java.security.Permissions());
            }
            client = S3Client.builder()
                    .region(region)
                    .endpointOverride(URI.create(base))
                    .credentialsProvider(creds)
                    .sslContextProvider(ctx != null ? software.amazon.awssdk.http.nio.netty.NettyNioAsyncHttpClient.Builder::sslContext : null)
                    .forcePathStyle(true)
                    .build();
        } catch (Exception ex) {
            System.out.println("BUILD FAIL: " + base + " :: " + summarize(ex));
            return;
        }

        try {
            var buckets = client.listBuckets(ListBucketsRequest.builder()).buckets();
            System.out.println("OK listBuckets: " + base);
            if (buckets != null) {
                for (Bucket b : buckets) System.out.println("   bucket: " + b.name() + " (created: " + b.creationDate() + ")");
            } else {
                System.out.println("   (no buckets returned)");
            }
        } catch (S3Exception ex) {
            System.out.println("listBuckets FAIL(" + ex.statusCode() + "): " + base + " :: " + summarize(ex));
        } catch (Exception ex) {
            System.out.println("listBuckets FAIL: " + base + " :: " + summarize(ex));
        } finally {
            client.close();
        }

        try {
            var client2 = S3Client.builder()
                    .region(region)
                    .endpointOverride(URI.create(base))
                    .credentialsProvider(creds)
                    .sslContextProvider(ctx != null ? software.amazon.awssdk.http.nio.netty.NettyNioAsyncHttpClient.Builder::sslContext : null)
                    .forcePathStyle(true)
                    .build();
            client2.headBucket(HeadBucketRequest.builder().bucket("selfie").build());
            System.out.println("OK headBucket(selfie): " + base);
        } catch (S3Exception ex) {
            System.out.println("headBucket(selfie) FAIL(" + ex.statusCode() + "): " + base + " :: " + summarize(ex));
        } catch (Exception ex) {
            System.out.println("headBucket(selfie) FAIL: " + base + " :: " + summarize(ex));
        } finally {
            try { client2.close(); } catch (Exception ignored) {}
        }
    }

    private static String summarize(Throwable t) {
        String m = t.getMessage();
        if (m == null) return t.getClass().getName();
        return (m.length() > 240 ? m.substring(0, 240) : m);
    }

    private record Endpoint(String url, boolean https) {}
}
