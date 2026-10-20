import type { NextConfig } from "next";

export default {
  // Portal web shell. Add images, redirects, and rewrites as real routes land.
  // Emits .next/standalone for the Docker image (portal/web/Dockerfile), which
  // runs its self-contained server.js instead of `next start`.
  output: "standalone",
} satisfies NextConfig;
