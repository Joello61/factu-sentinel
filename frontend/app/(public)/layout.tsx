import Link from "next/link";
import type { ReactNode } from "react";

/**
 * Pages publiques (connexion, inscription) : jamais l'App Shell, réservé à l'UI
 * authentifiée (components/app-shell/AppShell.tsx).
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-full flex-1 flex-col">
      <main className="flex flex-1 items-center justify-center p-6">
        <div className="w-full max-w-sm">{children}</div>
      </main>
      <footer className="flex flex-wrap justify-center gap-4 p-6 text-xs text-muted-foreground">
        <Link href="/mentions-legales" className="hover:text-foreground hover:underline">
          Mentions légales
        </Link>
        <Link href="/cgu" className="hover:text-foreground hover:underline">
          Conditions générales d&apos;utilisation
        </Link>
        <Link href="/politique-de-confidentialite" className="hover:text-foreground hover:underline">
          Politique de confidentialité
        </Link>
      </footer>
    </div>
  );
}
