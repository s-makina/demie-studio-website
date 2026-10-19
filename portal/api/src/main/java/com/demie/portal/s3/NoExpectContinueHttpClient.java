package com.demie.portal.s3;

import software.amazon.awssdk.http.ExecutableHttpRequest;
import software.amazon.awssdk.http.HttpExecuteRequest;
import software.amazon.awssdk.http.SdkHttpClient;

/**
 * Delegating HTTP client that removes the {@code Expect: 100-continue} header from every request.
 *
 * <p>The AWS SDK adds that header to all {@code PutObject}/{@code UploadPart} calls from its
 * internal {@code StreamingRequestInterceptor}, and it cannot be disabled through the SDK's own
 * configuration: the {@code S3Configuration} and {@code ApacheHttpClient.Builder} switches do not
 * remove it, and a user {@link software.amazon.awssdk.core.interceptor.ExecutionInterceptor} runs
 * before the SDK's internal one.
 *
 * <p>The MinIO instance behind this deployment's reverse proxy mishandles the header: it answers
 * the expectation and then returns a bare {@code 200 OK} with no ETag, never stores the object,
 * and <em>reports success to the SDK</em>. The consequence is that every server-side write -
 * previews, thumbnails, zip archives - disappears silently while the database records the media as
 * ready. Browser uploads are unaffected because presigned PUTs never carry the header, which is why
 * originals arrive but derivatives do not.
 *
 * <p>The real fix belongs in the reverse proxy / MinIO configuration; this wrapper keeps the API
 * working in the meantime. Dropping the header only means the body is sent without waiting for a
 * provisional response, which is safe here.
 */
final class NoExpectContinueHttpClient implements SdkHttpClient {

    private final SdkHttpClient delegate;

    NoExpectContinueHttpClient(SdkHttpClient delegate) {
        this.delegate = delegate;
    }

    @Override
    public ExecutableHttpRequest prepareRequest(HttpExecuteRequest request) {
        HttpExecuteRequest.Builder stripped = HttpExecuteRequest.builder()
                .request(request.httpRequest().toBuilder().removeHeader("Expect").build());
        request.contentStreamProvider().ifPresent(stripped::contentStreamProvider);
        request.metricCollector().ifPresent(stripped::metricCollector);
        return delegate.prepareRequest(stripped.build());
    }

    @Override
    public String clientName() {
        return delegate.clientName();
    }

    @Override
    public void close() {
        delegate.close();
    }
}
