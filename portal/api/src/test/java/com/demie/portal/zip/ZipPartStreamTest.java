package com.demie.portal.zip;

import org.junit.jupiter.api.Test;
import software.amazon.awssdk.services.s3.model.CompletedPart;

import java.io.OutputStream;
import java.util.ArrayList;
import java.util.List;
import java.util.Random;
import java.util.zip.ZipEntry;
import java.util.zip.ZipOutputStream;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

/**
 * Unit-verifies the streaming upload's part boundaries without S3: full 8MB
 * parts go up mid-stream (never small non-final parts), the tail ships as
 * the final part, and the byte count is exact. The live round-trip (real
 * MinIO) is covered by {@link ZipWorkerTest}.
 */
class ZipPartStreamTest {

    @Test
    void twentyMegabytesShipsAsTwoFullPartsPlusTail() throws Exception {
        List<byte[]> uploaded = new ArrayList<>();
        List<CompletedPart> parts = new ArrayList<>();
        ZipWorker.PartStream stream = new ZipWorker.PartStream("zips/1.zip", "upload-1", parts,
                (partNumber, bytes) -> {
                    uploaded.add(bytes);
                    return "etag-" + partNumber;
                });

        byte[] payload = new byte[20 * 1024 * 1024];
        new Random(9).nextBytes(payload);
        long written;
        try (OutputStream out = stream) {
            out.write(payload);
            written = payload.length;
        }

        assertEquals(3, uploaded.size(), "8MB + 8MB + 4MB tail");
        assertEquals(8 * 1024 * 1024, uploaded.get(0).length);
        assertEquals(8 * 1024 * 1024, uploaded.get(1).length);
        assertEquals(4 * 1024 * 1024, uploaded.get(2).length);
        assertEquals(3, parts.size());
        for (int i = 0; i < 3; i++) {
            assertEquals(i + 1, parts.get(i).partNumber());
            assertEquals("etag-" + (i + 1), parts.get(i).eTag());
        }
        assertEquals(written, stream.bytesWritten(), "byte count is exact");
        assertEquals(written, uploaded.stream().mapToLong(b -> b.length).sum());
    }

    @Test
    void smallArchiveShipsAsSingleFinalPart() throws Exception {
        List<byte[]> uploaded = new ArrayList<>();
        List<CompletedPart> parts = new ArrayList<>();
        ZipWorker.PartStream stream = new ZipWorker.PartStream("zips/2.zip", "upload-2", parts,
                (partNumber, bytes) -> {
                    uploaded.add(bytes);
                    return "etag-" + partNumber;
                });

        byte[] payload = new byte[1024];
        new Random(3).nextBytes(payload);
        try (ZipOutputStream zip = new ZipOutputStream(stream)) {
            zip.putNextEntry(new ZipEntry("small.bin"));
            zip.write(payload);
            zip.closeEntry();
        }

        assertEquals(1, uploaded.size(), "one small final part");
        assertEquals(1, parts.size());
        assertTrue(stream.bytesWritten() > payload.length, "ZIP envelope counted");
    }

    @Test
    void emptySelectionStillYieldsAValidObject() throws Exception {
        List<byte[]> uploaded = new ArrayList<>();
        List<CompletedPart> parts = new ArrayList<>();
        ZipWorker.PartStream stream = new ZipWorker.PartStream("zips/3.zip", "upload-3", parts,
                (partNumber, bytes) -> {
                    uploaded.add(bytes);
                    return "etag-" + partNumber;
                });

        try (ZipOutputStream zip = new ZipOutputStream(stream)) {
            zip.finish();
        }

        assertEquals(1, uploaded.size(), "empty archive still uploads one part");
        assertEquals(1, parts.size());
        assertEquals(uploaded.get(0).length, stream.bytesWritten());
    }
}
