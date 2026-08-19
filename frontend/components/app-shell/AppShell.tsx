import type { ReactNode } from "react";
import { Header } from "./Header";
import { Sidebar } from "./Sidebar";

/**
 * Structure de l'App Shell (docs/11-frontend-design-system.md, section 17) :
 * Header en tête, Sidebar de navigation + zone de contenu principal en dessous.
 * Réservé à l'UI authentifiée (section 18) — les pages publiques (connexion,
 * inscription, à construire en Phase 2) ne l'utiliseront pas.
 */
export function AppShell({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-full flex-1 flex-col">
      <Header />
      <div className="flex flex-1">
        <Sidebar />
        <main className="flex-1 p-6 md:p-8">{children}</main>
      </div>
    </div>
  );
}
