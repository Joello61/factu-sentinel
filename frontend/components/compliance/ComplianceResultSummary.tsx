import { ComplianceResultBadge } from "@/components/ui/ComplianceResultBadge";
import { ComplianceFindingCard } from "@/components/compliance/ComplianceFindingCard";
import { formatTimestamp } from "@/lib/format/date";
import type { ComplianceAnalysis, ComplianceResult } from "@/lib/api/types";

/**
 * Même ordre de priorité que le moteur (App\Compliance\Engine\Service\
 * ComplianceResultAggregator::PRECEDENCE, backend) : les findings les plus significatifs en
 * premier, pour que l'utilisateur pressé voie d'abord ce qui explique le résultat global,
 * jamais un ordre arbitraire (ex. celui renvoyé par la base). CONFORME/NON_APPLICABLE
 * n'apparaissent pas dans PRECEDENCE côté moteur (jamais responsables du résultat global) :
 * placés en dernier ici, CONFORME avant NON_APPLICABLE (un point vérifié vaut plus qu'un
 * point simplement ignoré).
 */
const SEVERITY_ORDER: ComplianceResult[] = [
  "NON_CONFORME",
  "INCERTAIN_REGLEMENTAIRE",
  "A_VERIFIER",
  "AVERTISSEMENT",
  "CONFORME",
  "NON_APPLICABLE",
];

/**
 * Carte de résultat d'une ComplianceAnalysis (docs/11-frontend-design-system.md, section 27 :
 * Compliance Result UI). N'affiche jamais elle-même les actions (lancer/relancer l'analyse,
 * modifier la facture) : ce sont des actions au niveau de la page appelante
 * (InvoiceDetail), pas de cette carte de présentation pure.
 */
export function ComplianceResultSummary({
  analysis,
  context,
}: {
  analysis: ComplianceAnalysis;
  context: { customerId: string; invoiceId: string };
}) {
  const findings = [...analysis.findings].sort(
    (a, b) => SEVERITY_ORDER.indexOf(a.result) - SEVERITY_ORDER.indexOf(b.result),
  );

  return (
    <div aria-live="polite" className="flex flex-col gap-4 rounded-md border border-border p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          {analysis.global_result ? <ComplianceResultBadge result={analysis.global_result} /> : null}
          {analysis.completed_at ? (
            <p className="text-xs text-muted-foreground">Analysée le {formatTimestamp(analysis.completed_at)}</p>
          ) : null}
        </div>
      </div>

      {findings.length > 0 ? (
        <div className="flex flex-col gap-3">
          {findings.map((finding) => (
            <ComplianceFindingCard key={finding.id} finding={finding} context={context} />
          ))}
        </div>
      ) : null}
    </div>
  );
}
