import type { ReactNode } from "react";
import { PlatformAdminAuthProvider } from "@/components/platform-admin/PlatformAdminAuthProvider";

/**
 * Route isolée (platform-admin) - décision actée avec l'utilisateur (plan Phase 15, ADR-009
 * option 3) : même application Next.js que l'espace tenant, mais contexte d'authentification
 * strictement distinct (PlatformAdminAuthProvider, jamais components/auth/AuthProvider.tsx).
 * Ce layout ne fournit que le contexte - la garde de session et la coquille visuelle vivent
 * dans (protected)/layout.tsx, /login restant volontairement en dehors (accès anonyme requis).
 */
export default function PlatformAdminLayout({ children }: { children: ReactNode }) {
  return <PlatformAdminAuthProvider>{children}</PlatformAdminAuthProvider>;
}
