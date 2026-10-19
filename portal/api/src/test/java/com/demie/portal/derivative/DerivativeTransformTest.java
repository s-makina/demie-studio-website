package com.demie.portal.derivative;

import net.coobird.thumbnailator.util.exif.ExifUtils;
import net.coobird.thumbnailator.util.exif.Orientation;
import org.junit.jupiter.api.Test;

import javax.imageio.ImageIO;
import javax.imageio.ImageReader;
import javax.imageio.stream.ImageInputStream;
import java.awt.Color;
import java.awt.Graphics2D;
import java.awt.image.BufferedImage;
import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.Comparator;
import java.util.Iterator;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

/**
 * Transform-level checks on the single resize call both derivatives go through.
 *
 * <p>Plain JUnit — no Spring context, no Postgres, no MinIO — so the loop is
 * deterministic and runs in about a second. Fixtures are built in-process with a
 * marker band whose position after rotation is known exactly.
 */
class DerivativeTransformTest {

    /** Thickness of the red marker band painted along the stored top edge. */
    private static final int BAND = 80;

    /**
     * EXIF orientation 6 ("Right side, top") means a viewer must rotate the
     * stored pixels 90° clockwise. A stored 800x600 with the marker along its
     * top edge therefore displays as portrait 600x800 with the marker down the
     * right-hand edge. A thumb that ignores orientation stays landscape with the
     * marker along the top — which is also what the gallery then lays out, since
     * it sizes the tile from the recorded thumb dimensions.
     */
    @Test
    void exifOrientationIsApplied() throws Exception {
        Path dir = Files.createTempDirectory("deriv-exif");
        try {
            // Exactly how makeDerivatives stages the downloaded object: a temp
            // file with NO extension.
            Path source = dir.resolve("original");
            Files.write(source, jpegWithOrientation(800, 600, 6));

            assertEquals(Orientation.RIGHT_TOP, readOrientation(source),
                    "fixture must actually carry EXIF orientation 6, otherwise this proves nothing");

            Path thumb = dir.resolve("thumb.jpg");
            DerivativeWorker.resize(source, thumb, DerivativeWorker.THUMB_MAX_PX);

            BufferedImage out = ImageIO.read(thumb.toFile());
            boolean topIsRed = isRed(out.getRGB(out.getWidth() / 2, 2));
            boolean rightIsRed = isRed(out.getRGB(out.getWidth() - 3, out.getHeight() / 2));

            assertTrue(out.getHeight() > out.getWidth(),
                    "orientation 6 displays as portrait, but the thumb came out "
                            + out.getWidth() + "x" + out.getHeight());
            assertTrue(rightIsRed, "marker should be down the right edge after rotation");
            assertTrue(!topIsRed, "marker should no longer run along the top edge");
        } finally {
            deleteTree(dir);
        }
    }

    // ------------------------------------------------------------- fixtures

    /** An 800x600-ish JPEG with a red marker band along the stored top edge. */
    private static byte[] jpegWithOrientation(int width, int height, int orientation)
            throws IOException {
        BufferedImage image = new BufferedImage(width, height, BufferedImage.TYPE_INT_RGB);
        Graphics2D graphics = image.createGraphics();
        graphics.setColor(Color.BLUE);
        graphics.fillRect(0, 0, width, height);
        graphics.setColor(Color.RED);
        graphics.fillRect(0, 0, width, BAND);
        graphics.dispose();

        ByteArrayOutputStream jpeg = new ByteArrayOutputStream();
        ImageIO.write(image, "jpeg", jpeg);
        byte[] bytes = jpeg.toByteArray();
        byte[] app1 = exifApp1(orientation);

        // Splice APP1 in after the JFIF APP0 segment: the Java JPEG reader
        // rejects a file whose APP0 is not the first marker after SOI.
        int insertAt = afterJfifApp0(bytes);

        byte[] spliced = new byte[bytes.length + app1.length];
        System.arraycopy(bytes, 0, spliced, 0, insertAt);
        System.arraycopy(app1, 0, spliced, insertAt, app1.length);
        System.arraycopy(bytes, insertAt, spliced, insertAt + app1.length,
                bytes.length - insertAt);
        return spliced;
    }

    /** Offset just past the APP0 segment (which starts right after SOI). */
    private static int afterJfifApp0(byte[] jpeg) {
        boolean app0 = jpeg[2] == (byte) 0xFF && jpeg[3] == (byte) 0xE0;
        if (!app0) {
            return 2;
        }
        int app0Length = ((jpeg[4] & 0xFF) << 8) | (jpeg[5] & 0xFF);
        return 2 + 2 + app0Length; // marker + its length field + payload
    }

    /** A minimal APP1 segment carrying a single IFD0 Orientation tag. */
    private static byte[] exifApp1(int orientation) {
        byte[] tiff = {
                'I', 'I',                            // little-endian
                0x2A, 0x00,                          // TIFF magic 42
                0x08, 0x00, 0x00, 0x00,              // IFD0 at offset 8
                0x01, 0x00,                          // one directory entry
                0x12, 0x01,                          // tag 0x0112 = Orientation
                0x03, 0x00,                          // type 3 = SHORT
                0x01, 0x00, 0x00, 0x00,              // count 1
                (byte) orientation, 0x00, 0x00, 0x00, // value, left-aligned
                0x00, 0x00, 0x00, 0x00,              // no next IFD
        };
        int payload = 6 + tiff.length; // "Exif\0\0" + TIFF block
        byte[] app1 = new byte[4 + payload];
        app1[0] = (byte) 0xFF;
        app1[1] = (byte) 0xE1;                       // APP1 marker
        app1[2] = (byte) ((payload + 2) >> 8);       // length includes itself
        app1[3] = (byte) ((payload + 2) & 0xFF);
        app1[4] = 'E';
        app1[5] = 'x';
        app1[6] = 'i';
        app1[7] = 'f';
        app1[8] = 0;
        app1[9] = 0;
        System.arraycopy(tiff, 0, app1, 10, tiff.length);
        return app1;
    }

    private static Orientation readOrientation(Path jpeg) throws IOException {
        try (ImageInputStream in = ImageIO.createImageInputStream(jpeg.toFile())) {
            Iterator<ImageReader> readers = ImageIO.getImageReaders(in);
            assertTrue(readers.hasNext(), "no JPEG reader");
            ImageReader reader = readers.next();
            try {
                reader.setInput(in);
                return ExifUtils.getExifOrientation(reader, 0);
            } finally {
                reader.dispose();
            }
        }
    }

    private static boolean isRed(int rgb) {
        int r = (rgb >> 16) & 0xFF;
        int g = (rgb >> 8) & 0xFF;
        int b = rgb & 0xFF;
        return r > 180 && g < 90 && b < 90;
    }

    private static void deleteTree(Path root) {
        try (var paths = Files.walk(root)) {
            paths.sorted(Comparator.reverseOrder()).forEach(path -> {
                try {
                    Files.deleteIfExists(path);
                } catch (IOException ignored) {
                    // best effort
                }
            });
        } catch (IOException ignored) {
            // best effort
        }
    }
}
