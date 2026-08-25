"use client";

import type { ReactNode } from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Activity, Building2, LogOut, ScrollText, Send, ShieldAlert } from "lucide-react";
import { usePlatformAdminAuth } from "@/components/platform-admin/PlatformAdminAuthProvider";

/**
 * Coquille visuelle de la surface Platform Administration - volontairement distincte du
 * reste de l'application (docs/11-frontend-design-system.md, ligne 650 : "visuellement et
 * structurellement distincte du reste de l'inventaire"), jamais AppShell/Header/Sidebar
 * (components/app-shell/*, réservés à l'espace tenant). Barre de navigation minimale : quatre
 * écrans seulement (US-PLATFORMADMIN-001 à 005).
 */
const NAV_ITEMS = [
  { href: "/platform-admin/organizations", label: "Organisations", icon: Building2 },
  { href: "/platform-admin/audit", label: "Audit", icon: ScrollText },
  { href: "/platform-admin/notifications", label: "Notifications", icon: Send },
  { href: "/platform-admin/health", label: "Santé", icon: Activity },
] as const;

export function PlatformAdminShell({ children }: { children: ReactNode }) {
  const { administrator, logout } = usePlatformAdminAuth();
  const pathname = usePathname();

  return (
    <div className="flex min-h-full flex-1 flex-col bg-background">
      <header className="border-b border-error/30 bg-[#1a1a1a] text-white">
        <div className="flex h-16 items-center justify-between px-4 md:px-6">
          <div className="flex items-center gap-2">
            <ShieldAlert aria-hidden="true" className="text-error" size={22} strokeWidth={1.75} />
            <span className="text-base font-semibold">Administration plateforme</span>
          </div>

          <div className="flex items-center gap-4">
            {administrator ? (
              <span className="hidden text-sm text-white/70 sm:inline">{administrator.email}</span>
            ) : null}
            <button
              type="button"
              onClick={() => {
                void logout();
              }}
              className="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium text-white/90 hover:bg-white/10"
            >
              <LogOut aria-hidden="true" size={16} strokeWidth={1.75} />
              Se déconnecter
            </button>
          </div>
        </div>

        <nav aria-label="Administration plateforme" className="flex gap-1 px-4 md:px-6">
          {NAV_ITEMS.map((item) => {
            const active = pathname === item.href || pathname.startsWith(`${item.href}/`);
            const Icon = item.icon;
            return (
              <Link
                key={item.href}
                href={item.href}
                aria-current={active ? "page" : undefined}
                className={`flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition-colors ${
                  active
                    ? "border-error text-white"
                    : "border-transparent text-white/70 hover:border-white/30 hover:text-white"
                }`}
              >
                <Icon aria-hidden="true" size={16} strokeWidth={1.75} />
                {item.label}
              </Link>
            );
          })}
        </nav>
      </header>

      <main className="flex-1 p-6 md:p-8">{children}</main>
    </div>
  );
}
