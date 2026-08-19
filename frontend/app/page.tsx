import { AppShell } from "@/components/app-shell/AppShell";

/**
 * Phase 1 - Technical Foundation (docs/12-roadmap.md) : l'App Shell affiché vide, sans
 * contenu métier. Les pages réelles (diagnostic, factures, etc.) arrivent phases
 * suivantes ; ce placeholder sera remplacé, pas complété sur place.
 */
export default function Home() {
  return (
    <AppShell>
      <h1 className="text-2xl font-semibold text-foreground">Bienvenue sur FactuSentinel</h1>
      <p className="mt-2 max-w-prose text-muted-foreground">
        Cette page sera remplacée par le diagnostic d’éligibilité une fois
        l’authentification et la configuration de l’entreprise disponibles.
      </p>
    </AppShell>
  );
}
