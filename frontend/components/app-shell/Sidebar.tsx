'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiRequest } from '@/lib/api/client';
import type { Organization, Role } from '@/lib/api/types';
import { NAV_ITEMS } from './nav-items';

/**
 * Navigation principale (docs/11-frontend-design-system.md, section 16-17). Visible en
 * permanence sur desktop ; masquée sur mobile pour cette Phase 1 (App Shell "vide") - le
 * menu hamburger interactif sera ajouté quand une première page authentifiée réelle
 * existera (section 14, 17), pour éviter de construire un état interactif jetable
 * maintenant.
 *
 * Rôle courant (Phase 14) : récupéré ici via GET /organizations/current plutôt que porté par
 * AuthProvider - le reste de l'application suit déjà un patron de chargement par page/
 * composant (ex. CustomerList), pas un cache global d'état organisation. `role` reste `null`
 * pendant le chargement initial : les entrées restreintes restent masquées par défaut (fail
 * secure côté affichage) plutôt que brièvement visibles avant la première réponse.
 */
export function Sidebar() {
  const [role, setRole] = useState<Role | null>(null);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const organization = await apiRequest<Organization>('/api/v1/organizations/current');
        if (!cancelled) {
          setRole(organization.role);
        }
      } catch {
        // Confort d'affichage uniquement : en cas d'échec, les entrées restreintes par rôle
        // restent simplement masquées (role reste null) - jamais une erreur bloquante pour la
        // navigation elle-même.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const visibleItems = NAV_ITEMS.filter((item) => !item.roles || (role !== null && item.roles.includes(role)));

  return (
    <nav
      aria-label="Navigation principale"
      className="hidden md:flex w-60 shrink-0 flex-col gap-1 border-r border-border bg-surface p-4"
    >
      {visibleItems.map(({ label, href, icon: Icon }) => (
        <Link
          key={href}
          href={href}
          className="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-foreground hover:bg-primary/10 hover:text-primary"
        >
          <Icon aria-hidden="true" size={18} strokeWidth={1.75} />
          {label}
        </Link>
      ))}
    </nav>
  );
}
