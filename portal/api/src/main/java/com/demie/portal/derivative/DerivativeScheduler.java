package com.demie.portal.derivative;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
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

    private static final Logger log = LoggerFactory.getLogger(DerivativeScheduler.class);

    private final DerivativeWorker worker;
    private final int batchSize;
    private final int backfillBatchSize;

    public DerivativeScheduler(DerivativeWorker worker,
                               @Value("${DERIVATIVE_BATCH_SIZE:10}") int batchSize,
                               @Value("${DERIVATIVE_BACKFILL_BATCH:200}") int backfillBatchSize) {
        this.worker = worker;
        this.batchSize = batchSize;
        this.backfillBatchSize = backfillBatchSize;
    }

    @Scheduled(fixedDelayString = "${DERIVATIVE_POLL_MILLIS:30000}")
    public void sweep() {
        worker.processPendingBatch(batchSize);
        // Legacy rows (derivative-ready before thumb dimensions existed) get
        // their aspect ratio filled in so the client masonry lays out exactly.
        worker.backfillDimensions(backfillBatchSize);
    }

    /**
     * Re-queues poison-quarantined rows (one full {@code MAX_ATTEMPTS} budget
     * each pass) so a transient S3 outage doesn't leave gallery tiles blank
     * forever; the next regular sweeps pick the rows back up. Runs on its own,
     * much slower cadence: a source that is genuinely broken fails its budget
     * every time and lands back here, which is bounded work and keeps the
     * error column fresh.
     */
    @Scheduled(fixedDelayString = "${DERIVATIVE_RETRY_MILLIS:900000}")
    public void requeueQuarantined() {
        int requeued = worker.requeueQuarantined(batchSize);
        if (requeued > 0) {
            log.info("re-queued {} quarantined media rows for derivative retry", requeued);
        }
    }
}
