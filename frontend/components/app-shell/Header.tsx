"use client";

import Link from "next/link";
import { Bell, ChevronDown, LogOut, Settings, ShieldCheck } from "lucide-react";
import { DropdownMenu } from "radix-ui";
import { useAuth } from "@/components/auth/AuthProvider";

/**
 * Header applicatif (docs/11-frontend-design-system.md, section 17) : logo, accès rapide
 * aux notifications, menu de compte. AppLayout ne rend ce composant qu'une fois status
 * "authenticated" (app/(app)/layout.tsx), donc `user` est toujours renseigné ici.
 */
export function Header() {
  const { user, logout } = useAuth();

  return (
    <header className="flex h-16 shrink-0 items-center justify-between border-b border-border bg-surface px-4 md:px-6">
      <Link href="/" className="flex items-center gap-2 text-foreground">
        <ShieldCheck aria-hidden="true" className="text-primary" size={22} strokeWidth={1.75} />
        <span className="text-base font-semibold">FactuSentinel</span>
      </Link>

      <div className="flex items-center gap-2">
        <button
          type="button"
          aria-label="Notifications"
          className="rounded-md p-2 text-muted-foreground hover:bg-primary/10 hover:text-primary"
        >
          <Bell aria-hidden="true" size={20} strokeWidth={1.75} />
        </button>

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
