package com.demie.portal.s3;

import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.core.env.Environment;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.context.TestPropertySource;

/** THROWAWAY probe: which profile and bucket names does the sliced context see? */
@SpringBootTest(classes = {S3Config.class, S3Service.class})
@ActiveProfiles("test")
@TestPropertySource(properties = {
        "spring.autoconfigure.exclude=" +
                "org.springframework.boot.autoconfigure.jdbc.DataSourceAutoConfiguration," +
                "org.springframework.boot.autoconfigure.flyway.FlywayAutoConfiguration"
})
class BucketResolutionProbe {

    @Autowired
    private S3Service s3;

    @Autowired
    private Environment environment;

    @Test
    void report() {
        System.out.println("[RES] active profiles: "
                + String.join(",", environment.getActiveProfiles()));
        System.out.println("[RES] originalsBucket=" + s3.originalsBucket());
        System.out.println("[RES] thumbsBucket=" + s3.thumbsBucket());
        System.out.println("[RES] property S3_BUCKET_THUMBS="
                + environment.getProperty("S3_BUCKET_THUMBS"));
        System.out.println("[RES] property spring.profiles.active="
                + environment.getProperty("spring.profiles.active"));
    }
}
