package com.demie.portal.s3;

import com.sun.net.httpserver.HttpServer;
import org.junit.jupiter.api.Test;
import software.amazon.awssdk.auth.credentials.AwsBasicCredentials;
import software.amazon.awssdk.auth.credentials.StaticCredentialsProvider;
import software.amazon.awssdk.core.sync.RequestBody;
import software.amazon.awssdk.regions.Region;
import software.amazon.awssdk.services.s3.S3Client;

import java.net.InetSocketAddress;
import java.net.URI;
import java.nio.charset.StandardCharsets;
import java.util.List;
import java.util.concurrent.CopyOnWriteArrayList;
import java.util.concurrent.TimeUnit;

/**
 * Throwaway diagnostic: captures the raw request the AWS SDK sends (and, for comparison, the
 * request curl sends) against a local HTTP server, so we can see exactly which headers differ.
 * Never touches a real bucket.
 */
class SdkRequestCapture {

    private static final List<String> LOG = new CopyOnWriteArrayList<>();

    private void sdkPut(int port, String label, Boolean expectContinue, Boolean chunkedEncoding) {
        sdkPut(port, label, expectContinue, chunkedEncoding, false);
    }

    private void sdkPut(int port, String label, Boolean expectContinue, Boolean chunkedEncoding,
            boolean stripExpect) {
        LOG.add("=== AWS SDK putObject (" + label + ") ===");
        var builder = S3Client.builder()
                .region(Region.US_EAST_1)
                .endpointOverride(URI.create("http://127.0.0.1:" + port))
                .credentialsProvider(StaticCredentialsProvider.create(
                        AwsBasicCredentials.create("access", "secret")));
        if (chunkedEncoding != null) {
            builder.serviceConfiguration(software.amazon.awssdk.services.s3.S3Configuration.builder()
                    .pathStyleAccessEnabled(true)
                    .chunkedEncodingEnabled(chunkedEncoding)
                    .build());
        } else {
            builder.forcePathStyle(true);
        }
        if (expectContinue != null) {
            builder.httpClientBuilder(software.amazon.awssdk.http.apache.ApacheHttpClient.builder()
                    .expectContinueEnabled(expectContinue));
        }
        if (stripExpect) {
            builder.overrideConfiguration(c -> c.addExecutionInterceptor(
                    new software.amazon.awssdk.core.interceptor.ExecutionInterceptor() {
                        @Override
                        public software.amazon.awssdk.http.SdkHttpRequest modifyHttpRequest(
                                software.amazon.awssdk.core.interceptor.Context.ModifyHttpRequest ctx,
                                software.amazon.awssdk.core.interceptor.ExecutionAttributes a) {
                            return ctx.httpRequest().toBuilder().removeHeader("Expect").build();
                        }
                    }));
        }
        S3Client s3 = builder.build();
        try {
            s3.putObject(b -> b.bucket("bucket").key("key.txt").contentType("text/plain"),
                    RequestBody.fromBytes("hello".getBytes(StandardCharsets.UTF_8)));
        } catch (Exception e) {
            LOG.add("    [error] " + e);
        } finally {
            s3.close();
        }
    }

    private void sdkExplicitHttpClient(int port) {
        LOG.add("=== AWS SDK putObject (explicit httpClient + expectContinueEnabled=false) ===");
        var httpClient = software.amazon.awssdk.http.apache.ApacheHttpClient.builder()
                .expectContinueEnabled(false)
                .build();
        S3Client s3 = S3Client.builder()
                .region(Region.US_EAST_1)
                .endpointOverride(URI.create("http://127.0.0.1:" + port))
                .credentialsProvider(StaticCredentialsProvider.create(
                        AwsBasicCredentials.create("access", "secret")))
                .forcePathStyle(true)
                .httpClient(httpClient)
                .build();
        try {
            s3.putObject(b -> b.bucket("bucket").key("key.txt").contentType("text/plain"),
                    RequestBody.fromBytes("hello".getBytes(StandardCharsets.UTF_8)));
        } catch (Exception e) {
            LOG.add("    [error] " + e);
        } finally {
            s3.close();
        }
    }

    private void sdkNetty(int port) {
        LOG.add("=== AWS SDK async putObject (Netty) ===");
        var s3 = software.amazon.awssdk.services.s3.S3AsyncClient.builder()
                .region(Region.US_EAST_1)
                .endpointOverride(URI.create("http://127.0.0.1:" + port))
                .credentialsProvider(StaticCredentialsProvider.create(
                        AwsBasicCredentials.create("access", "secret")))
                .forcePathStyle(true)
                .httpClientBuilder(software.amazon.awssdk.http.nio.netty.NettyNioAsyncHttpClient.builder())
                .build();
        try {
            s3.putObject(b -> b.bucket("bucket").key("key.txt").contentType("text/plain"),
                    software.amazon.awssdk.core.async.AsyncRequestBody.fromBytes(
                            "hello".getBytes(StandardCharsets.UTF_8))).join();
        } catch (Exception e) {
            LOG.add("    [error] " + e);
        } finally {
            s3.close();
        }
    }

    private void sdkWrapped(int port) {
        LOG.add("=== AWS SDK putObject (NoExpectContinueHttpClient wrapper) ===");
        S3Client s3 = S3Client.builder()
                .region(Region.US_EAST_1)
                .endpointOverride(URI.create("http://127.0.0.1:" + port))
                .credentialsProvider(StaticCredentialsProvider.create(
                        AwsBasicCredentials.create("access", "secret")))
                .forcePathStyle(true)
                .httpClient(new NoExpectContinueHttpClient(
                        software.amazon.awssdk.http.apache.ApacheHttpClient.builder()
                                .expectContinueEnabled(false)
                                .build()))
                .build();
        try {
            s3.putObject(b -> b.bucket("bucket").key("key.txt").contentType("text/plain"),
                    RequestBody.fromBytes("hello".getBytes(StandardCharsets.UTF_8)));
        } catch (Exception e) {
            LOG.add("    [error] " + e);
        } finally {
            s3.close();
        }
    }

    @Test
    void capture() throws Exception {
        HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);
        server.createContext("/", exchange -> {
            LOG.add("--- " + exchange.getRequestMethod() + " " + exchange.getRequestURI());
            exchange.getRequestHeaders().forEach((name, values) -> LOG.add("    " + name + ": " + values));
            byte[] body = exchange.getRequestBody().readAllBytes();
            LOG.add("    [body] " + body.length + " bytes");
            exchange.sendResponseHeaders(200, -1);
            exchange.close();
        });
        server.start();
        int port = server.getAddress().getPort();
        String url = "http://127.0.0.1:" + port + "/bucket/key.txt";

        sdkPut(port, "default", null, null);
        sdkPut(port, "expectContinueEnabled=false", false, null);
        sdkPut(port, "chunkedEncodingEnabled=false", null, false);
        sdkPut(port, "chunkedEncoding=false + expectContinue=false", false, false);
        sdkPut(port, "chunkedEncoding=false + interceptor strips Expect", true, false);
        sdkExplicitHttpClient(port);
        sdkNetty(port);
        sdkWrapped(port);

        for (String expect : new String[] {null, "100-continue"}) {
            LOG.add("=== curl PUT " + (expect == null ? "(no Expect)" : "Expect: " + expect) + " ===");
            List<String> cmd = new java.util.ArrayList<>(List.of("curl.exe", "-sS", "-X", "PUT",
                    "-H", "Content-Type: text/plain"));
            if (expect != null) {
                cmd.add("-H");
                cmd.add("Expect: " + expect);
            }
            cmd.add("--data-binary");
            cmd.add("hello");
            cmd.add("-o");
            cmd.add("NUL");
            cmd.add(url);
            Process p = new ProcessBuilder(cmd).redirectErrorStream(true).start();
            p.getInputStream().readAllBytes();
            p.waitFor(30, TimeUnit.SECONDS);
        }

        server.stop(0);
        LOG.forEach(System.out::println);
    }
}
