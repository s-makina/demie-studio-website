package com.demie.portal.s3;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.context.TestPropertySource;

import javax.imageio.ImageIO;
import java.awt.Color;
import java.awt.Graphics2D;
import java.awt.image.BufferedImage;
import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.security.MessageDigest;
import java.util.HexFormat;

/** THROWAWAY probe: do server-side writes store the exact bytes we send? */
@SpringBootTest
@ActiveProfiles("test")
@TestPropertySource(properties = "derivative.worker.enabled=false")
class S3WriteProbe {

    @Autowired
    private S3Service s3;

    @Test
    void serverSidePutRoundTrip() throws Exception {
        byte[] png = testPng(800, 600);
        String key = "probe/fidelity-" + System.nanoTime() + ".png";
        String bucket = s3.originalsBucket();

        s3.putObject(bucket, key, "image/png", png.length, new ByteArrayInputStream(png));
        System.out.println("[WRITE] bucket=" + bucket + " key=" + key + " sent=" + png.length + "b");
        System.out.println("[WRITE] stored-size=" + s3.objectSize(bucket, key));

        try (InputStream in = s3.getObject(bucket, key)) {
            byte[] read = in.readAllBytes();
            System.out.println("[WRITE] read=" + read.length + "b md5-match="
                    + HexFormat.of().formatHex(sha256(png))
                            .equals(HexFormat.of().formatHex(sha256(read))));
            BufferedImage image = ImageIO.read(new ByteArrayInputStream(read));
            System.out.println("[WRITE] decoded=" + (image == null ? "null"
                    : image.getWidth() + "x" + image.getHeight()));
        }
        s3.deleteObject(bucket, key);
    }

    private static byte[] sha256(byte[] data) throws Exception {
        return MessageDigest.getInstance("SHA-256").digest(data);
    }

    private static byte[] testPng(int width, int height) throws Exception {
        BufferedImage image = new BufferedImage(width, height, BufferedImage.TYPE_INT_RGB);
        Graphics2D graphics = image.createGraphics();
        graphics.setColor(Color.BLUE);
        graphics.fillRect(0, 0, width, height);
        graphics.setColor(Color.YELLOW);
        graphics.fillOval(width / 4, height / 4, width / 2, height / 2);
        graphics.dispose();
        ByteArrayOutputStream out = new ByteArrayOutputStream();
        ImageIO.write(image, "png", out);
        return out.toByteArray();
    }
}
