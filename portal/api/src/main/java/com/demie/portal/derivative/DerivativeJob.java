package com.demie.portal.derivative;

/**
 * One claimed unit of derivative work: the media row id plus the attempt
 * number this try runs as. Attempts are bounded ({@code MAX_ATTEMPTS}); past
 * that the row quarantines as {@code failed} (poison pill).
 */
public record DerivativeJob(long mediaId, int attempt) {
}
