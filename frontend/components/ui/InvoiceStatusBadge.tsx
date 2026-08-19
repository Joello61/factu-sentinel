import type { InvoiceStatus } from "@/lib/api/types";

/**
 * Badge de statut d'analyse d'une facture (docs/11-frontend-design-system.md, section 26 :
 * couleur + label, jamais la couleur seule). Distinct du badge de résultat de conformité
 * (Compliance Result UI, Phase 6, inexistant à ce stade) : ce badge ne représente que l'état
 * du cycle de vie d'analyse (docs/08-api-specification.md, section 28), jamais un verdict de
 * conformité.
 */
const STATUS_CONFIG: Record<InvoiceStatus, { label: string; className: string }> = {
  DRAFT: { label: "Brouillon", className: "bg-border text-foreground" },
  READY_FOR_ANALYSIS: { label: "Prête pour analyse", className: "bg-info/15 text-info" },
  ANALYZED: { label: "Analysée", className: "bg-success/15 text-success" },
  ANALYSIS_STALE: { label: "Analyse à relancer", className: "bg-warning/15 text-warning" },
};

export function InvoiceStatusBadge({ status }: { status: InvoiceStatus }) {
  const config = STATUS_CONFIG[status];

  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${config.className}`}>
      {config.label}
    </span>
  );
}
