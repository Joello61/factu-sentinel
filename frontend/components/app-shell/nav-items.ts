import {
  ClipboardCheck,
  FileText,
  Users,
  History,
  LayoutDashboard,
  Bell,
  Settings,
  type LucideIcon,
} from "lucide-react";

export interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
}

/**
 * Navigation principale — docs/11-frontend-design-system.md, section 16. Aucune section
 * "Factures" en tant que production/émission : "Factures" ici signifie exclusivement
 * l'analyse de conformité de factures existantes (docs/04-product-requirements.md,
 * section 7 et 30).
 *
 * Les routes ci-dessous n'existent pas encore (elles seront construites phase par phase,
 * docs/12-roadmap.md) : la navigation matérialise l'architecture de l'information dès la
 * Phase 1, cohérent avec le rôle d'"App Shell" de cette phase.
 */
export const NAV_ITEMS: NavItem[] = [
  { label: "Diagnostic", href: "/diagnostic", icon: ClipboardCheck },
  { label: "Factures", href: "/invoices", icon: FileText },
  { label: "Clients", href: "/customers", icon: Users },
  { label: "Historique", href: "/history", icon: History },
  { label: "Dashboard", href: "/dashboard", icon: LayoutDashboard },
  { label: "Notifications", href: "/notifications", icon: Bell },
  { label: "Paramètres", href: "/settings", icon: Settings },
];
