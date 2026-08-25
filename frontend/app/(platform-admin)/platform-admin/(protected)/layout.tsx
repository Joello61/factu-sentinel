"use client";

import { useEffect, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { PlatformAdminShell } from "@/components/platform-admin/PlatformAdminShell";
import { usePlatformAdminAuth } from "@/components/platform-admin/PlatformAdminAuthProvider";

/**
 * Garde de session pour toute l'UI Platform Administration authentifiée - confort
 * d'expérience uniquement (frontend/CLAUDE.md, section 6), le backend revalide
 * systématiquement (App\Shared\Security\PlatformAdminAuthenticationListener). Même patron
 * que app/(app)/layout.tsx, jamais partagé avec lui (contextes d'authentification distincts).
 */
export default function PlatformAdminProtectedLayout({ children }: { children: ReactNode }) {
  const { status } = usePlatformAdminAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === "anonymous" || status === "mfa_required") {
      router.replace("/platform-admin/login");
    }
  }, [status, router]);

  if (status !== "authenticated") {
    return null;
  }

  return <PlatformAdminShell>{children}</PlatformAdminShell>;
}
