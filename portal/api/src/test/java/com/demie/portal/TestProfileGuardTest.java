package com.demie.portal;

import org.junit.jupiter.api.Test;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;

import java.nio.file.Files;
import java.nio.file.Path;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.List;
import java.util.stream.Stream;

import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

/**
 * Guards the developer database from the test suite (post-mortem for a wiped
 * local account).
 *
 * <p>A {@code @SpringBootTest} without {@code @ActiveProfiles("test")} resolves
 * its datasource from {@code application.yml}, whose default is the real local
 * database ({@code demie_portal}). Every integration test starts with a
 * {@code TRUNCATE}, so one class that forgot the profile deleted local accounts
 * and projects. Sliced contexts ({@code @SpringBootTest(classes = ...)}, e.g.
 * the S3-only ones) never open a datasource and are exempt.
 *
 * <p>{@code src/test/resources/application.properties} already defaults the
 * whole test classpath to the {@code test} profile; this scan keeps the opt-in
 * explicit so the guarantee never depends on a single missed annotation.
 */
class TestProfileGuardTest {

    @Test
    void fullContextTestsActivateTheTestProfile() throws Exception {
        Path classes = Path.of("target", "test-classes");
        assertTrue(Files.isDirectory(classes),
                "expected compiled test classes at " + classes.toAbsolutePath()
                        + " — run the suite through Maven");

        List<String> offenders = new ArrayList<>();
        int scanned = 0;
        try (Stream<Path> tree = Files.walk(classes)) {
            for (Path file : tree.filter(path -> path.toString().endsWith(".class")).toList()) {
                String name = classes.relativize(file).toString()
                        .replace('\\', '/')
                        .replace('/', '.')
                        .replace(".class", "");
                if (name.contains("$")) {
                    continue;
                }
                SpringBootTest boot = Class.forName(name, false, getClass().getClassLoader())
                        .getAnnotation(SpringBootTest.class);
                if (boot == null || boot.classes().length > 0) {
                    continue;
                }
                scanned++;
                ActiveProfiles profiles = Class.forName(name, false, getClass().getClassLoader())
                        .getAnnotation(ActiveProfiles.class);
                if (profiles == null || !Arrays.asList(profiles.value()).contains("test")) {
                    offenders.add(name);
                }
            }
        }

        assertFalse(scanned == 0, "scan found no full-context @SpringBootTest classes");
        assertTrue(offenders.isEmpty(),
                "these @SpringBootTest classes must declare @ActiveProfiles(\"test\") "
                        + "or they will TRUNCATE the developer database: " + offenders);
    }
}
