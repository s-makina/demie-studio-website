package com.demie.portal.zip;

import org.springframework.boot.autoconfigure.condition.ConditionalOnProperty;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

/**
 * Periodic sweep feeding {@link ZipWorker}: starts pending builds (bounded
 * concurrency) and retires past-expiry finished jobs. Disabled in tests via
 * {@code zip.worker.enabled=false} so tests drive the worker deterministically
 * instead.
 */
@Component
@ConditionalOnProperty(name = "zip.worker.enabled", havingValue = "true", matchIfMissing = true)
public class ZipScheduler {

    private final ZipWorker worker;

    public ZipScheduler(ZipWorker worker) {
        this.worker = worker;
    }

    @Scheduled(fixedDelayString = "${ZIP_POLL_MILLIS:15000}")
    public void sweep() {
        worker.expireSwept();
        worker.buildNext();
    }
}
