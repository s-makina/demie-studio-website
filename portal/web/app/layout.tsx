import type { Metadata } from "next";
import "./globals.css";

const SITE_NAME = "Demie Photography";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "";

if (process.env.NODE_ENV !== "test" && !API_URL) {
  console.warn(
    "NEXT_PUBLIC_API_URL is not set. Point it at the running portal API (e.g. http://localhost:8080)."
  );
}

export const metadata: Metadata = {
  title: {
    default: SITE_NAME,
    template: `%s | ${SITE_NAME}`,
  },
  description:
    "Client portal — browse, select, and download your shoot without re-sending files.",
  icons: {
    icon: "/favicon.svg",
  },
};

export default function RootLayout({
  children,
}: LayoutProps<"/">) {
  return (
    <html lang="en" className="h-full antialiased">
      <body className="min-h-full flex flex-col">{children}</body>
    </html>
  );
}
