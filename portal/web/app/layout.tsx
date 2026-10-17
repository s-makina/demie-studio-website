import type { Metadata } from "next";
import { Cormorant_Garamond, Plus_Jakarta_Sans } from "next/font/google";
import "./globals.css";

const SITE_NAME = "Demie Photography";

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "";

if (process.env.NODE_ENV !== "test" && !API_URL) {
  console.warn(
    "NEXT_PUBLIC_API_URL is not set. Point it at the running portal API (e.g. http://localhost:8080)."
  );
}

const cormorant = Cormorant_Garamond({
  subsets: ["latin"],
  variable: "--font-serif",
  weight: ["300", "400", "500", "600"],
  style: ["normal", "italic"],
  display: "swap",
});

const jakarta = Plus_Jakarta_Sans({
  subsets: ["latin"],
  variable: "--font-sans",
  weight: ["300", "400", "500", "600", "700"],
  display: "swap",
});

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
}: {
  children: React.ReactNode;
}) {
  return (
    <html
      lang="en"
      className={`h-full antialiased ${cormorant.variable} ${jakarta.variable}`}
    >
      <body className="min-h-full flex flex-col font-sans bg-background text-foreground selection:bg-accent/20 selection:text-foreground">
        {children}
      </body>
    </html>
  );
}
