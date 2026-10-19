package com.demie.portal.s3;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.context.TestPropertySource;
import software.amazon.awssdk.auth.credentials.AwsBasicCredentials;
import software.amazon.awssdk.auth.credentials.AwsCredentialsProvider;
import software.amazon.awssdk.auth.credentials.StaticCredentialsProvider;
import software.amazon.awssdk.regions.Region;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.HeadBucketRequest;
import software.amazon.awssdk.services.s3.model.ListBucketsRequest;
import software.amazon.awssdk.services.s3.model.S3Exception;
import software.amazon.awssdk.services.s3.model.Bucket;

import java.net.URI;
import java.util.List;

@SpringBootTest(classes = {S3Config.class, S3Service.class})
@ActiveProfiles("test")
@TestPropertySource(properties = {
        "spring.autoconfigure.exclude=" +
                "org.springframework.boot.autoconfigure.jdbc.DataSourceAutoConfiguration," +
                "org.springframework.boot.autoconfigure.flyway.FlywayAutoConfiguration"
})
class S3EndpointProbe {

    @Autowired
    private S3Client baseClient;

    @Test
    void findWorkingS3Endpoint() {
        String accessKey = System.getenv("S3_ACCESS_KEY");
        String secretKey = System.getenv("S3_SECRET_KEY");
        if (accessKey == null || accessKey.isBlank() || secretKey == null || secretKey.isBlank()) {
            System.out.println("SKIP: S3_ACCESS_KEY/S3_SECRET_KEY not set");
            return;
        }
        AwsCredentialsProvider creds = StaticCredentialsProvider.create(
                AwsBasicCredentials.create(accessKey, secretKey));
        Region region = baseClient.serviceClientConfiguration().region();

        List<String> candidates = List.of(
                "http://salvation-minio-bdd8cc-102-202-45-205.sslip.io",
                "http://salvation-minio-bdd8cc-102-202-45-205.sslip.io:9000",
                "http://salvation-minio-bdd8cc-102-202-45-205.sslip.io:4569",
                "http://salvation-minio-bdd8cc-102-202-45-205.sslip.io/s3",
                "http://salvation-minio-bdd8cc-102-202-45-205.sslip.io/api",
                "https://salvation-minio-bdd8cc-102-202-45-205.sslip.io",
                "https://salvation-minio-bdd8cc-102-202-45-205.sslip.io:9000",
                "https://salvation-minio-bdd8cc-102-202-45-205.sslip.io/s3",
                "https://salvation-minio-bdd8cc-102-202-45-205.sslip.io/api"
        );

        for (String base : candidates) {
            tryEndpoint(base, creds, region);
        }
    }

    private void tryEndpoint(String base, AwsCredentialsProvider creds, Region region) {
        S3Client client;
        try {
            client = S3Client.builder()
                    .region(region)
                    .endpointOverride(URI.create(base))
                    .credentialsProvider(creds)
                    .forcePathStyle(true)
                    .build();
        } catch (Exception e) {
            System.out.println("BUILD FAIL: " + base + " :: " + summarize(e));
            return;
        }

        try {
            var response = client.listBuckets(ListBucketsRequest.builder().build());
            System.out.println("OK listBuckets: " + base);
            if (response.buckets() != null) {
                for (Bucket b : response.buckets()) {
                    System.out.println("   bucket: " + b.name() + " (created: " + b.creationDate() + ")");
                }
            } else {
                System.out.println("   (no buckets returned)");
            }
        } catch (S3Exception e) {
            System.out.println("listBuckets FAIL(" + e.statusCode() + "): " + base + " :: " + summarize(e));
        } catch (Exception e) {
            System.out.println("listBuckets FAIL: " + base + " :: " + summarize(e));
        } finally {
            client.close();
        }

        S3Client client2 = null;
        try {
            client2 = S3Client.builder()
                    .region(region)
                    .endpointOverride(URI.create(base))
                    .credentialsProvider(creds)
                    .forcePathStyle(true)
                    .build();
            client2.headBucket(HeadBucketRequest.builder().bucket("demie-studio").build());
            System.out.println("OK headBucket(demie-studio): " + base);
        } catch (S3Exception e) {
            System.out.println("headBucket(demie-studio) FAIL(" + e.statusCode() + "): " + base + " :: " + summarize(e));
        } catch (Exception e) {
            System.out.println("headBucket(demie-studio) FAIL: " + base + " :: " + summarize(e));
        } finally {
            if (client2 != null) {
                client2.close();
            }
        }
    }

    private String summarize(Throwable t) {
        String m = t.getMessage();
        if (m == null) {
            return t.getClass().getName();
        }
        return (m.length() > 260 ? m.substring(0, 260) : m);
    }
}
