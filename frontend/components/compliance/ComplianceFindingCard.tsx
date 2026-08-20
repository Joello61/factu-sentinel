"use client";

import Link from "next/link";
import { useState } from "react";
import { ChevronDown, Sparkles } from "lucide-react";
import { Collapsible } from "radix-ui";
import { Button } from "@/components/ui/Button";
import { ComplianceResultBadge } from "@/components/ui/ComplianceResultBadge";
import { apiRequest, ApiError } from "@/lib/api/client";
import { formatBusinessDate } from "@/lib/format/date";
import type { ComplianceFinding, ComplianceFindingExplanation, ConfidenceLevel } from "@/lib/api/types";

const CONFIDENCE_LABELS: Record<ConfidenceLevel, string> = {
  ELEVE: "Élevée",
  MOYEN: "Moyenne",
  FAIBLE: "Faible",
};

/**
 * Résout le lien de correction d'un finding (docs/11-frontend-design-system.md, section 29 :
 * "lien direct vers ce champ dans le formulaire de la facture, si possible") à partir de
 * l'identifiant technique du champ concerné (`related_field`, ex. "customer.siren") et du
 * contexte déjà chargé par l'appelant -- jamais en essayant d'extraire un identifiant depuis
 * `related_field` lui-même, qui n'est qu'un chemin technique, pas un identifiant.
 */
export function resolveCorrectionLink(
  relatedField: string | null,
  context: { customerId: string; invoiceId: string },
): string | null {
  if (null === relatedField) {
    return null;
  }
  if (relatedField.startsWith("customer.")) {
    return `/customers/${context.customerId}`;
  }
  if (relatedField.startsWith("invoice.")) {
    return `/invoices/${context.invoiceId}/edit`;
  }
  return null;
}

/**
 * Un ComplianceFinding, progressive disclosure à 3 niveaux (docs/11-frontend-design-system.md,
 * section 28) : niveau 1 (badge + message, toujours visible), niveau 2 (correction_action,
 * toujours visible quand présente -- FR-COMPLIANCE-003), niveau 3 (détail de la règle,
 * derrière une divulgation accessible au clavier, Radix Collapsible déjà installé).
 */
/**
 * État de la reformulation IA (docs/11-frontend-design-system.md, section 30 : déclenchée à
 * la demande, jamais automatique). "email-required" est distinct de "error" -- message et
 * action de récupération différents (../../CLAUDE.md frontend, section 9).
 */
type ExplanationState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "loaded"; explanation: string }
  | { status: "error" }
  | { status: "email-required" };

export function ComplianceFindingCard({
  finding,
  context,
}: {
  finding: ComplianceFinding;
  context: { customerId: string; invoiceId: string };
}) {
  const correctionLink = resolveCorrectionLink(finding.related_field, context);
  const [explanationState, setExplanationState] = useState<ExplanationState>({ status: "idle" });

  async function handleRequestExplanation() {
    setExplanationState({ status: "loading" });
    try {
      const result = await apiRequest<ComplianceFindingExplanation>(
        `/api/v1/compliance-findings/${finding.id}/explanations`,
        { method: "POST" },
      );
      setExplanationState({ status: "loaded", explanation: result.explanation });
    } catch (error) {
      if (error instanceof ApiError && error.code === "EMAIL_VERIFICATION_REQUIRED") {
        setExplanationState({ status: "email-required" });
        return;
      }
      // Le message par défaut de finding.message reste affiché tel quel juste au-dessus
      // (docs/06-technical-architecture.md, section 14-15 : repli systématique) -- cet état
      // n'efface jamais rien, il ajoute seulement une note discrète.
      setExplanationState({ status: "error" });
    }
  }

  return (
    <div className="rounded-md border border-border p-4">
      <div className="flex flex-col gap-2">
        <ComplianceResultBadge result={finding.result} />
        <p className="text-sm text-foreground">{finding.message}</p>

        {finding.correction_action ? (
          <div className="flex flex-wrap items-center gap-2 rounded-md bg-surface px-3 py-2">
            <p className="text-sm text-foreground">{finding.correction_action}</p>
            {correctionLink ? (
              <Link href={correctionLink} className="text-sm font-medium text-primary hover:underline">
                Corriger maintenant
              </Link>
            ) : null}
          </div>
        ) : null}

        {explanationState.status === "idle" || explanationState.status === "loading" ? (
          <Button
            type="button"
            variant="secondary"
            loading={explanationState.status === "loading"}
            onClick={handleRequestExplanation}
            className="w-fit"
          >
            <Sparkles aria-hidden="true" size={14} />
            Expliquer autrement
          </Button>
        ) : null}

        {/*
         * Distinction visuelle permanente avec le message ci-dessus (docs/11-frontend-design-system.md,
         * section 31 : Trust & Transparency) -- fond et libellé "Explication assistée" toujours
         * présents, jamais la même apparence que le message déterministe du moteur.
         */}
        {explanationState.status === "loaded" ? (
          <div className="rounded-md border border-info/30 bg-info/5 px-3 py-2">
            <p className="text-xs font-medium text-info">Explication assistée</p>
            <p className="mt-1 text-sm text-foreground">{explanationState.explanation}</p>
          </div>
        ) : null}

        {explanationState.status === "error" ? (
          <div className="flex flex-wrap items-center gap-2">
            <p className="text-xs text-muted-foreground">
              L&apos;explication assistée n&apos;est pas disponible pour le moment.
            </p>
            <button
              type="button"
              onClick={handleRequestExplanation}
              className="text-xs font-medium text-primary hover:underline"
            >
              Réessayer
            </button>
          </div>
        ) : null}

        {explanationState.status === "email-required" ? (
          <p className="text-xs text-muted-foreground">
            Vérifiez votre adresse email pour utiliser l&apos;explication assistée.
          </p>
        ) : null}
      </div>

      <Collapsible.Root className="mt-3">
        <Collapsible.Trigger className="flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground [&[data-state=open]>svg]:rotate-180">
          <ChevronDown aria-hidden="true" size={14} className="transition-transform" />
          Détails de la règle
        </Collapsible.Trigger>
        <Collapsible.Content>
          <dl className="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 text-xs text-muted-foreground sm:grid-cols-2">
            <div>
              <dt className="font-medium">Règle</dt>
              <dd>{finding.rule.id}</dd>
            </div>
            <div>
              <dt className="font-medium">Version</dt>
              <dd>{finding.rule.version}</dd>
            </div>
            <div>
              <dt className="font-medium">Applicable depuis</dt>
              <dd>{formatBusinessDate(finding.rule.effective_from)}</dd>
            </div>
            {finding.rule.effective_until ? (
              <div>
                <dt className="font-medium">Applicable jusqu&apos;au</dt>
                <dd>{formatBusinessDate(finding.rule.effective_until)}</dd>
              </div>
            ) : null}
            <div>
              <dt className="font-medium">Source réglementaire</dt>
              <dd>{finding.rule.source_reference}</dd>
            </div>
            <div>
              <dt className="font-medium">Confiance</dt>
              <dd>{CONFIDENCE_LABELS[finding.rule.confidence_level]}</dd>
            </div>
            {finding.related_field ? (
              <div>
                <dt className="font-medium">Champ observé</dt>
                <dd>{finding.related_field}</dd>
              </div>
            ) : null}
            {finding.observed_value ? (
              <div>
                <dt className="font-medium">Valeur observée</dt>
                <dd>{finding.observed_value}</dd>
              </div>
            ) : null}
          </dl>
        </Collapsible.Content>
      </Collapsible.Root>
    </div>
  );
}
