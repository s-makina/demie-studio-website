package com.demie.portal.account;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.ApplicationArguments;
import org.springframework.boot.ApplicationRunner;
import org.springframework.stereotype.Component;

/**
 * Creates the first photographer (admin) account when no admin exists yet.
 * Defaults seed a local dev login ({@code admin@demiestudio.local} /
 * {@code Admin@123}); override with {@code ADMIN_EMAIL} / {@code ADMIN_PASSWORD}
 * env vars in any shared deployment. Runs after Flyway has migrated the
 * schema. No-op when an admin already exists.
 */
@Component
public class AdminBootstrap implements ApplicationRunner {

    private static final Logger log = LoggerFactory.getLogger(AdminBootstrap.class);

    private final AccountService accounts;
    private final String adminEmail;
    private final String adminPassword;
    private final String adminName;

    public AdminBootstrap(AccountService accounts,
                          @Value("${ADMIN_EMAIL:admin@demiestudio.local}") String adminEmail,
                          @Value("${ADMIN_PASSWORD:Admin@123}") String adminPassword,
                          @Value("${ADMIN_NAME:Photographer}") String adminName) {
        this.accounts = accounts;
        this.adminEmail = adminEmail;
        this.adminPassword = adminPassword;
        this.adminName = adminName;
    }

    @Override
    public void run(ApplicationArguments args) {
        if (adminEmail.isBlank() || adminPassword.isBlank()) {
            return;
        }
        if (accounts.adminExists()) {
            return;
        }
        accounts.createAdmin(adminEmail, adminPassword, adminName);
        log.info("Bootstrapped admin account for {}", adminEmail);
    }
}
