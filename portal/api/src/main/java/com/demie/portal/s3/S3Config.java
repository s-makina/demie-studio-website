package com.demie.portal.s3;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import software.amazon.awssdk.auth.credentials.AwsBasicCredentials;
import software.amazon.awssdk.auth.credentials.AnonymousCredentialsProvider;
import software.amazon.awssdk.auth.credentials.StaticCredentialsProvider;
import software.amazon.awssdk.http.apache.ApacheHttpClient;
import software.amazon.awssdk.regions.Region;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.S3Configuration;
import software.amazon.awssdk.services.s3.presigner.S3Presigner;

import java.net.URI;
import java.time.Duration;

@Configuration
public class S3Config {

    @Value("${S3_ENDPOINT:}")
    private String endpoint;

    @Value("${S3_ACCESS_KEY:}")
    private String accessKey;

    @Value("${S3_SECRET_KEY:}")
    private String secretKey;

    // TCP connect timeout for S3 requests.
    @Value("${S3_CONNECT_TIMEOUT_SECONDS:10}")
    private long connectTimeoutSeconds;

    // Socket read timeout. The derivative worker streams whole original
    // objects (photographer-size photos) through this client, so leave slack
    // well above the observed 2s TTFB plus transfer time; the SDK default is
    // far too tight to survive a slow stretch on the remote MinIO link.
    @Value("${S3_SOCKET_TIMEOUT_SECONDS:120}")
    private long socketTimeoutSeconds;

    // Evict pooled connections that have been idle this long. The remote
    // MinIO front end silently black-holes old idle connections, and a
    // long-lived worker reusing one of those reads nothing until the socket
    // timeout fires ("Unable to execute HTTP request: Read timed out") on
    // every attempt. Evicting early makes each derivative sweep dial a fresh
    // connection instead of trusting ephemerally-dead pooled ones.
    @Value("${S3_CONNECTION_MAX_IDLE_SECONDS:60}")
    private long connectionMaxIdleSeconds;

    @Bean
    public S3ClientFactory s3ClientFactory() {
        return this::buildClient;
    }

    @Bean
    public S3Client s3Client(S3ClientFactory factory) {
        return factory.newClient();
    }

    private S3Client buildClient() {
        var builder = S3Client.builder()
                .region(Region.US_EAST_1)
                .credentialsProvider(credentialsProvider())
                // The SDK's internal StreamingRequestInterceptor always adds
                // "Expect: 100-continue" to PutObject calls, and this deployment's
                // MinIO front end mishandles that header: it replies 200 without a body
                // and silently drops the object. Stripping the header is the only
                // supported way to avoid it - see NoExpectContinueHttpClient.
                .httpClient(new NoExpectContinueHttpClient(
                        ApacheHttpClient.builder()
                                .expectContinueEnabled(false)
                                .connectionTimeout(Duration.ofSeconds(connectTimeoutSeconds))
                                .socketTimeout(Duration.ofSeconds(socketTimeoutSeconds))
                                .connectionMaxIdleTime(Duration.ofSeconds(connectionMaxIdleSeconds))
                                .build()))
                // The same front end also mishandles the SDK's default "aws-chunked"
                // streaming payload, so sign the body normally instead.
                .serviceConfiguration(S3Configuration.builder()
                        .pathStyleAccessEnabled(true)
                        .chunkedEncodingEnabled(false)
                        .build());

        if (endpoint != null && !endpoint.isBlank()) {
            builder.endpointOverride(URI.create(endpoint));
        }

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
