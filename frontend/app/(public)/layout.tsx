import type { ReactNode } from "react";

/**
 * Pages publiques (connexion, inscription) : jamais l'App Shell, réservé à l'UI
 * authentifiée (components/app-shell/AppShell.tsx).
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
  return (
    <main className="flex min-h-full flex-1 items-center justify-center p-6">
      <div className="w-full max-w-sm">{children}</div>
    </main>
  );
}
