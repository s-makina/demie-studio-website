package com.demie.portal.derivative;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.autoconfigure.condition.ConditionalOnProperty;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Component;

/**
 * Periodic sweep feeding {@link DerivativeWorker}. Disabled in tests via
 * {@code derivative.worker.enabled=false} so tests drive the worker
 * deterministically instead.
 */
@Component
@ConditionalOnProperty(name = "derivative.worker.enabled", havingValue = "true", matchIfMissing = true)
public class DerivativeScheduler {

    private final DerivativeWorker worker;
    private final int batchSize;

    public DerivativeScheduler(DerivativeWorker worker,
                               @Value("${DERIVATIVE_BATCH_SIZE:10}") int batchSize) {
        this.worker = worker;
        this.batchSize = batchSize;
    }

    @Scheduled(fixedDelayString = "${DERIVATIVE_POLL_MILLIS:30000}")
    public void sweep() {
        worker.processPendingBatch(batchSize);
    }
}
