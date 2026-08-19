import Link from "next/link";
import { NAV_ITEMS } from "./nav-items";

/**
 * Navigation principale (docs/11-frontend-design-system.md, section 16-17). Visible en
 * permanence sur desktop ; masquée sur mobile pour cette Phase 1 (App Shell "vide") — le
 * menu hamburger interactif sera ajouté quand une première page authentifiée réelle
 * existera (section 14, 17), pour éviter de construire un état interactif jetable
 * maintenant.
 */
export function Sidebar() {
  return (
    <nav
      aria-label="Navigation principale"
      className="hidden md:flex w-60 shrink-0 flex-col gap-1 border-r border-border bg-surface p-4"
    >
      {NAV_ITEMS.map(({ label, href, icon: Icon }) => (
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
