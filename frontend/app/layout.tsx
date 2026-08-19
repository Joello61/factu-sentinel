import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";

// Police retenue par le design system (docs/11-frontend-design-system.md, section 7) :
// Inter, avec system-ui/sans-serif en repli si le chargement échoue.
const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "FactuSentinel",
  description:
    "Assistant de conformité à la facturation électronique pour les micro-entrepreneurs, indépendants, freelances et TPE françaises.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="fr" className={`${inter.variable} h-full antialiased`}>
      <body className="min-h-full flex flex-col">{children}</body>
    </html>
  );
}
