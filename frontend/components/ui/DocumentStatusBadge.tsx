import { AlertTriangle, CheckCircle2, Loader2, Upload, type LucideIcon } from "lucide-react";
import type { DocumentProcessingStatus } from "@/lib/api/types";

/**
 * Badge de statut de traitement d'un document (docs/11-frontend-design-system.md, section
 * 26 : couleur + icône + label). Distinct de ComplianceResultBadge -- ce badge ne représente
 * jamais un résultat de conformité, uniquement l'état du pipeline d'extraction (Phase 7).
 */
const STATUS_CONFIG: Record<DocumentProcessingStatus, { label: string; className: string; Icon: LucideIcon; spin?: boolean }> = {
  UPLOADED: { label: "En attente de traitement", className: "bg-info/15 text-info", Icon: Upload },
  PROCESSING: { label: "Traitement en cours", className: "bg-info/15 text-info", Icon: Loader2, spin: true },
  PARSED: { label: "Traitement en cours", className: "bg-info/15 text-info", Icon: Loader2, spin: true },
  VALIDATED: { label: "Traité", className: "bg-success/15 text-success", Icon: CheckCircle2 },
  FAILED: { label: "Échec du traitement", className: "bg-warning/15 text-warning", Icon: AlertTriangle },
};

export function DocumentStatusBadge({ status }: { status: DocumentProcessingStatus }) {
  const { label, className, Icon, spin } = STATUS_CONFIG[status];

  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${className}`}>
      <Icon aria-hidden="true" size={14} className={spin ? "motion-reduce:animate-none animate-spin" : undefined} />
      {label}
    </span>
  );
}
