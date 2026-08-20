import { AlertOctagon, AlertTriangle, CheckCircle2, MinusCircle, type LucideIcon } from "lucide-react";
import type { DashboardGlobalStatus } from "@/lib/api/types";

/**
 * Badge des 4 états agrégés du Dashboard (docs/08-api-specification.md, section 33, décision
 * produit Phase 9) -- couleur + icône + label, jamais la couleur seule, même discipline que
 * ComplianceResultBadge. Icône volontairement distincte de ComplianceResultBadge (AlertOctagon
 * plutôt que XCircle) pour ATTENTION_REQUISE : jamais la même apparence qu'un NON_CONFORME de
 * finding précis, ces deux badges répondant à des questions différentes (../../CLAUDE.md
 * frontend, section 8).
 */
const STATUS_CONFIG: Record<DashboardGlobalStatus, { label: string; className: string; Icon: LucideIcon }> = {
  AUCUNE_ANALYSE: { label: "Aucune analyse", className: "bg-border text-muted-foreground", Icon: MinusCircle },
  CONFORME: { label: "Conforme", className: "bg-success/15 text-success", Icon: CheckCircle2 },
  AVERTISSEMENT: { label: "Avertissement", className: "bg-warning/15 text-warning", Icon: AlertTriangle },
  ATTENTION_REQUISE: { label: "Attention requise", className: "bg-error/15 text-error", Icon: AlertOctagon },
};

export function DashboardStatusBadge({ status }: { status: DashboardGlobalStatus }) {
  const { label, className, Icon } = STATUS_CONFIG[status];

  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium ${className}`}>
      <Icon aria-hidden="true" size={16} />
      {label}
    </span>
  );
}
