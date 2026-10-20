package com.demie.portal.s3;

import software.amazon.awssdk.services.s3.S3Client;

/**
 * Builds fully configured {@link S3Client}s the way {@link S3Config} would, so
 * callers that run long batches against remote object storage can isolate
 * themselves from shared HTTP connection pool state.
 *
 * <p>Why this exists: the Dokploy MinIO front end intermittently black-holes
 * individual live connections (verified by concurrent differential probes —
 * fresh JVM clients sail through while a long-lived API worker wedges on the
 * same objects). For operations that must not stick, build a fresh client per
 * batch and close it when done.
 */
public interface S3ClientFactory {

    /** A new client with its own connection pool; caller must close it. */
    S3Client newClient();
}
