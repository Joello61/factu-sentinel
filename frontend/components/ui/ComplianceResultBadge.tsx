import { AlertTriangle, CheckCircle2, HelpCircle, MinusCircle, ShieldQuestion, XCircle, type LucideIcon } from "lucide-react";
import type { ComplianceResult } from "@/lib/api/types";

/**
 * Badge des six états de résultat de conformité (docs/11-frontend-design-system.md, sections
 * 5, 26, 27 : couleur + icône + label, jamais la couleur seule). Réutilise délibérément les
 * tokens sémantiques génériques (success/warning/error/info) pour CONFORME/AVERTISSEMENT/
 * NON_CONFORME/A_VERIFIER -- le design system autorise des teintes de base proches ("même si
 * la teinte de base peut être proche", section 5) : la distinction vient de l'icône et du
 * label, jamais de la seule couleur, et de la discipline d'usage (jamais réutilisé pour un
 * message générique hors conformité). Seul INCERTAIN_REGLEMENTAIRE a besoin d'un token dédié
 * (--color-uncertain, violet, suggéré explicitement par le design system, aucun équivalent
 * sémantique existant).
 *
 * NON_CONFORME reste ici une petite pastille (badge), jamais un encart d'erreur pleine
 * largeur -- c'est cette différence de forme, en plus de l'icône, qui la distingue de
 * l'erreur technique (../../CLAUDE.md frontend, section 8 ; design system section 27,
 * règle absolue), rendue séparément par l'appelant (jamais par ce composant).
 */
const RESULT_CONFIG: Record<ComplianceResult, { label: string; className: string; Icon: LucideIcon }> = {
  CONFORME: { label: "Conforme", className: "bg-success/15 text-success", Icon: CheckCircle2 },
  NON_CONFORME: { label: "Non conforme", className: "bg-error/15 text-error", Icon: XCircle },
  AVERTISSEMENT: { label: "Avertissement", className: "bg-warning/15 text-warning", Icon: AlertTriangle },
  A_VERIFIER: { label: "À vérifier", className: "bg-info/15 text-info", Icon: HelpCircle },
  NON_APPLICABLE: { label: "Non applicable", className: "bg-border text-muted-foreground", Icon: MinusCircle },
  INCERTAIN_REGLEMENTAIRE: { label: "Incertain (réglementation)", className: "bg-uncertain/15 text-uncertain", Icon: ShieldQuestion },
};

export function ComplianceResultBadge({ result }: { result: ComplianceResult }) {
  const { label, className, Icon } = RESULT_CONFIG[result];

  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${className}`}>
      <Icon aria-hidden="true" size={14} />
      {label}
    </span>
  );
}
