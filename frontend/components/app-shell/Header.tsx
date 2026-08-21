"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Bell, Building2, ChevronDown, LogOut, Settings, ShieldCheck } from "lucide-react";
import { DropdownMenu } from "radix-ui";
import { apiRequest, apiRequestPaginated } from "@/lib/api/client";
import { useAuth } from "@/components/auth/AuthProvider";
import type { AppNotification, UserOrganizationMembership } from "@/lib/api/types";

/**
 * Header applicatif (docs/11-frontend-design-system.md, section 17) : logo, accès rapide
 * aux notifications, menu de compte. AppLayout ne rend ce composant qu'une fois status
 * "authenticated" (app/(app)/layout.tsx), donc `user` est toujours renseigné ici.
 *
 * Phase 14 : le bouton cloche mène désormais à /notifications (badge de compte non lu) ;
 * "Changer d'organisation" n'apparaît dans le menu que si l'utilisateur appartient à plus
 * d'une organisation (docs/08-api-specification.md, section 9).
 *
 * GET /notifications est paginé (section 34) : le badge interroge la page la plus large
 * autorisée (per_page=100) plutôt que d'introduire un endpoint de comptage dédié, non
 * documenté par ailleurs - un volume de notifications dépassant 100 pour un TPE/micro-
 * entrepreneur solo ou une petite équipe (persona primaire, `03-market-analysis.md`) sous-
 * estimerait alors le badge, compromis jugé acceptable pour cette échelle plutôt que
 * d'inventer un nouvel endpoint.
 */
export function Header() {
  const { user, logout } = useAuth();
  const [unreadCount, setUnreadCount] = useState(0);
  const [multiOrganization, setMultiOrganization] = useState(false);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const [notifications, organizations] = await Promise.all([
          apiRequestPaginated<AppNotification>("/api/v1/notifications?per_page=100"),
          apiRequest<UserOrganizationMembership[]>("/api/v1/auth/me/organizations"),
        ]);
        if (!cancelled) {
          setUnreadCount(notifications.data.filter((notification) => notification.read_at === null).length);
          setMultiOrganization(organizations.length > 1);
        }
      } catch {
        // Confort d'affichage uniquement : en cas d'échec, le badge reste à zéro et le
        // sélecteur d'organisation reste masqué, sans bloquer le reste du Header.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <header className="flex h-16 shrink-0 items-center justify-between border-b border-border bg-surface px-4 md:px-6">
      <Link href="/" className="flex items-center gap-2 text-foreground">
        <ShieldCheck aria-hidden="true" className="text-primary" size={22} strokeWidth={1.75} />
        <span className="text-base font-semibold">FactuSentinel</span>
      </Link>

      <div className="flex items-center gap-2">
        <Link
          href="/notifications"
          aria-label={unreadCount > 0 ? `Notifications (${unreadCount} non lues)` : "Notifications"}
          className="relative rounded-md p-2 text-muted-foreground hover:bg-primary/10 hover:text-primary"
        >
          <Bell aria-hidden="true" size={20} strokeWidth={1.75} />
          {unreadCount > 0 ? (
            <span
              aria-hidden="true"
              className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-medium text-white"
            >
              {unreadCount > 9 ? "9+" : unreadCount}
            </span>
          ) : null}
        </Link>

        <DropdownMenu.Root>
          <DropdownMenu.Trigger asChild>
            <button
              type="button"
              className="flex items-center gap-1 rounded-md px-2 py-1.5 text-sm font-medium text-foreground hover:bg-primary/10 hover:text-primary"
            >
              {user?.email ?? "Mon compte"}
              <ChevronDown aria-hidden="true" size={16} strokeWidth={1.75} />
            </button>
          </DropdownMenu.Trigger>

          <DropdownMenu.Portal>
            <DropdownMenu.Content
              align="end"
              sideOffset={8}
              className="min-w-48 rounded-md border border-border bg-surface p-1 shadow-lg"
            >
              {multiOrganization ? (
                <DropdownMenu.Item asChild>
                  <Link
                    href="/select-organization"
                    className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-foreground outline-none hover:bg-primary/10 hover:text-primary"
                  >
                    <Building2 aria-hidden="true" size={16} strokeWidth={1.75} />
                    Changer d&apos;organisation
                  </Link>
                </DropdownMenu.Item>
              ) : null}
              <DropdownMenu.Item asChild>
                <Link
                  href="/settings"
                  className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-foreground outline-none hover:bg-primary/10 hover:text-primary"
                >
                  <Settings aria-hidden="true" size={16} strokeWidth={1.75} />
                  Paramètres
                </Link>
              </DropdownMenu.Item>
              <DropdownMenu.Item
                onSelect={() => {
                  void logout();
                }}
                className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-error outline-none hover:bg-error/10"
              >
                <LogOut aria-hidden="true" size={16} strokeWidth={1.75} />
                Se déconnecter
              </DropdownMenu.Item>
            </DropdownMenu.Content>
          </DropdownMenu.Portal>
        </DropdownMenu.Root>
      </div>
    </header>
  );
}
