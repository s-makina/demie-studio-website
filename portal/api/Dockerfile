# syntax=docker/dockerfile:1

# Dokploy Compose build: context is portal/api (see portal/docker-compose.yml).
# Same shape as saas/daylight/backend/Dockerfile. Spring Boot app-native env
# (DB_*, S3_*, ...) drives the build-free runtime config.

# --- build -------------------------------------------------------------------
FROM eclipse-temurin:21-jdk-alpine AS builder
RUN apk add --no-cache maven
WORKDIR /app

# Copy the POM first so dependency layers cache across src changes. The build
# context is portal/api (single module; no shared library install step).
COPY pom.xml .
RUN mvn -q -B dependency:go-offline

COPY src src
RUN mvn -q -B package -DskipTests

# --- run ---------------------------------------------------------------------
FROM eclipse-temurin:21-jre-alpine AS runner
WORKDIR /app
COPY --from=builder /app/target/*.jar app.jar

RUN addgroup -S appgroup && adduser -S appuser -G appgroup

USER appuser

EXPOSE 8080
CMD ["java", "-jar", "app.jar"]
