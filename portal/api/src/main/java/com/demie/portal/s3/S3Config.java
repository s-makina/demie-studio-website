package com.demie.portal.s3;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import software.amazon.awssdk.auth.credentials.AwsBasicCredentials;
import software.amazon.awssdk.auth.credentials.AnonymousCredentialsProvider;
import software.amazon.awssdk.auth.credentials.StaticCredentialsProvider;
import software.amazon.awssdk.regions.Region;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.S3Configuration;
import software.amazon.awssdk.services.s3.presigner.S3Presigner;

import java.net.URI;

@Configuration
public class S3Config {

    @Value("${S3_ENDPOINT:}")
    private String endpoint;

    @Value("${S3_ACCESS_KEY:}")
    private String accessKey;

    @Value("${S3_SECRET_KEY:}")
    private String secretKey;

    @Bean
    public S3Client s3Client() {
        var builder = S3Client.builder()
                .region(Region.US_EAST_1)
                .credentialsProvider(credentialsProvider());

        if (endpoint != null && !endpoint.isBlank()) {
            builder.endpointOverride(URI.create(endpoint));
        }

        // Force path-style access for MinIO/local S3-compatible endpoints.
        builder.forcePathStyle(true);

        return builder.build();
    }

    @Bean
    public S3Presigner s3Presigner() {
        var builder = S3Presigner.builder()
                .region(Region.US_EAST_1)
                .credentialsProvider(credentialsProvider());

        if (endpoint != null && !endpoint.isBlank()) {
            builder.endpointOverride(URI.create(endpoint));
        }

        // Must match S3Client's path-style access: without this the presigner
        // emits virtual-hosted-style URLs (https://bucket.host/key) which
        // MinIO behind a hostname-routed reverse proxy cannot resolve.
        builder.serviceConfiguration(S3Configuration.builder()
                .pathStyleAccessEnabled(true)
                .build());

        return builder.build();
    }

    private software.amazon.awssdk.auth.credentials.AwsCredentialsProvider credentialsProvider() {
        // S3Config sits in the s3 package; it is reused by the sliced
        // integration test (S3PresignIT) which runs without S3 env. When keys
        // are absent we use anonymous credentials so the beans build and the
        // context loads; real requests then fail fast, and the IT skips before
        // making any.
        if (accessKey != null && !accessKey.isBlank() && secretKey != null && !secretKey.isBlank()) {
            return StaticCredentialsProvider.create(
                    AwsBasicCredentials.create(accessKey, secretKey));
        }
        return AnonymousCredentialsProvider.create();
    }
}
