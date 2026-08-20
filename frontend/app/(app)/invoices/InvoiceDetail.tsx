'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiRequest, apiRequestPaginated, ApiError } from '@/lib/api/client';
import { InvoiceStatusBadge } from '@/components/ui/InvoiceStatusBadge';
import { Button } from '@/components/ui/Button';
import { ComplianceResultSummary } from '@/components/compliance/ComplianceResultSummary';
import { formatBusinessDate } from '@/lib/format/date';
import { formatAmount } from '@/lib/format/amount';
import type {
  Customer,
  Invoice,
  ComplianceAnalysis,
  ComplianceAnalysisSummary,
} from '@/lib/api/types';

type ViewState =
  | { status: 'loading' }
  | { status: 'not-found' }
  | { status: 'error'; message: string }
  | {
      status: 'ready';
      invoice: Invoice;
      customer: Customer | null;
      analysis: ComplianceAnalysis | null;
    };

/**
 * Distingue explicitement l'erreur métier (précondition manquante, ex. contexte fiscal non
 * configuré : 409) de l'erreur technique (l'analyse n'a pas pu être réalisée) -- jamais l'une
 * pour l'autre, et jamais confondue avec un résultat NON_CONFORME (../../../CLAUDE.md
 * frontend, section 8, règle absolue).
 */
type TriggerState =
  | { status: 'idle' }
  | { status: 'loading' }
  | { status: 'business-error'; message: string }
  | { status: 'technical-error'; message: string };

const OPERATION_TYPE_LABELS: Record<Invoice['operation_type'], string> = {
  VENTE_BIEN: 'Vente de bien',
  PRESTATION_SERVICE: 'Prestation de service',
  MIXTE: 'Mixte',
};

const CUSTOMER_TYPE_LABELS: Record<Customer['customer_type'], string> = {
  PROFESSIONNEL_FRANCAIS: 'Professionnel français',
  PARTICULIER: 'Particulier',
  PROFESSIONNEL_ETRANGER: 'Professionnel étranger',
};

/**
 * Dernière ComplianceAnalysis d'une facture (docs/08-api-specification.md, section 29) :
 * réutilise les deux endpoints existants (liste paginée triée plus récent d'abord, puis
 * lecture de l'analyse complète avec ses findings) plutôt que d'ajouter un endpoint dédié.
 */
async function fetchLatestAnalysis(invoiceId: string): Promise<ComplianceAnalysis | null> {
  const { data } = await apiRequestPaginated<ComplianceAnalysisSummary>(
    `/api/v1/invoices/${invoiceId}/compliance-analyses?per_page=1`,
  );
  if (0 === data.length) {
    return null;
  }
  return apiRequest<ComplianceAnalysis>(`/api/v1/compliance-analyses/${data[0].id}`);
}

/**
 * Détail d'une facture (docs/11-frontend-design-system.md, section 32) et de son résultat de
 * conformité le plus récent (section 27-29, US-COMPLIANCE-002/003/004/006/006bis) : lignes,
 * client, statut d'analyse, déclenchement/relance de l'analyse, correction.
 */
export function InvoiceDetail({ invoiceId }: { invoiceId: string }) {
  const [state, setState] = useState<ViewState>({ status: 'loading' });
  const [triggerState, setTriggerState] = useState<TriggerState>({ status: 'idle' });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const invoice = await apiRequest<Invoice>(`/api/v1/invoices/${invoiceId}`);
        let customer: Customer | null = null;
        try {
          customer = await apiRequest<Customer>(`/api/v1/customers/${invoice.customer_id}`);
        } catch {
          customer = null;
        }

        // Invariant vérifié côté backend (Invoice::refreshReadinessStatus()/markStale()) :
        // seules ANALYZED/ANALYSIS_STALE ont une ComplianceAnalysis à récupérer.
        let analysis: ComplianceAnalysis | null = null;
        if ('ANALYZED' === invoice.status || 'ANALYSIS_STALE' === invoice.status) {
          try {
            analysis = await fetchLatestAnalysis(invoiceId);
          } catch {
            analysis = null;
          }
        }

        if (!cancelled) {
          setState({ status: 'ready', invoice, customer, analysis });
        }
      } catch (error) {
        if (cancelled) {
          return;
        }
        if (error instanceof ApiError && error.status === 404) {
          setState({ status: 'not-found' });
          return;
        }
        setState({
          status: 'error',
          message: 'Impossible de charger cette facture pour le moment.',
        });
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [invoiceId]);

  async function handleTriggerAnalysis() {
    if ('ready' !== state.status) {
      return;
    }
    setTriggerState({ status: 'loading' });

    try {
      const analysis = await apiRequest<ComplianceAnalysis>(
        `/api/v1/invoices/${invoiceId}/compliance-analyses`,
        {
          method: 'POST',
          // Une clé fraîche à chaque clic : un nouveau clic est toujours une nouvelle
          // analyse voulue, jamais un rejeu (../../../CLAUDE.md racine, section 11). Le
          // double clic est déjà empêché structurellement par Button (disabled pendant
          // loading), donc jamais deux clés générées pour une seule intention utilisateur.
          headers: { 'Idempotency-Key': crypto.randomUUID() },
        },
      );
      setState((current) =>
        'ready' !== current.status
          ? current
          : { ...current, invoice: { ...current.invoice, status: 'ANALYZED' }, analysis },
      );
      setTriggerState({ status: 'idle' });
    } catch (error) {
      if (error instanceof ApiError && error.status === 409) {
        // Message déjà sûr et en français clair (App\Compliance\Engine\Service\
        // RunComplianceAnalysisService::doRun(), relayé tel quel par ApiExceptionListener).
        setTriggerState({ status: 'business-error', message: error.message });
        return;
      }
      setTriggerState({
        status: 'technical-error',
        message: "L'analyse n'a pas pu être réalisée pour le moment. Réessayez dans quelques instants.",
      });
    }
  }

  if (state.status === 'loading') {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  if (state.status === 'not-found') {
    return (
      <p className="text-sm text-muted-foreground">
        Cette facture n&apos;existe pas ou n&apos;est plus disponible.
      </p>
    );
  }

  if (state.status === 'error') {
    return (
      <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
        {state.message}
      </p>
    );
  }

  const { invoice, customer, analysis } = state;

  return (
    <div className="flex max-w-3xl flex-col gap-6">
      <div>
        <Link href="/invoices" className="text-xs font-medium text-primary hover:underline">
          ← Retour aux factures
        </Link>
        <div className="mt-2 flex flex-wrap items-center gap-3">
          <h1 className="text-2xl font-semibold text-foreground">
            {invoice.invoice_number ?? 'Facture sans numéro'}
          </h1>
          <InvoiceStatusBadge status={invoice.status} />
          <Link href={`/invoices/${invoice.id}/edit`} className="text-xs font-medium text-primary hover:underline">
            Modifier la facture
          </Link>
        </div>
      </div>

      <dl className="grid grid-cols-1 gap-4 rounded-md border border-border p-4 sm:grid-cols-2">
        <div>
          <dt className="text-xs font-medium text-muted-foreground">Client</dt>
          <dd className="text-sm text-foreground">
            {customer
              ? `${customer.name} (${CUSTOMER_TYPE_LABELS[customer.customer_type]})`
              : '-'}
          </dd>
        </div>
        <div>
          <dt className="text-xs font-medium text-muted-foreground">
            Date d&apos;émission
          </dt>
          <dd className="text-sm text-foreground">
            {formatBusinessDate(invoice.issue_date)}
          </dd>
        </div>
        <div>
          <dt className="text-xs font-medium text-muted-foreground">
            Nature de l&apos;opération
          </dt>
          <dd className="text-sm text-foreground">
            {OPERATION_TYPE_LABELS[invoice.operation_type]}
          </dd>
        </div>
        <div>
          <dt className="text-xs font-medium text-muted-foreground">Origine</dt>
          <dd className="text-sm text-foreground">
            {invoice.source === 'SAISIE_MANUELLE'
              ? 'Saisie manuelle'
              : 'Document importé'}
          </dd>
        </div>
      </dl>

      <div>
        <h2 className="text-sm font-semibold text-foreground">Lignes</h2>
        <div className="mt-2 overflow-x-auto rounded-md border border-border">
          <table className="w-full text-left text-sm">
            <thead className="bg-surface text-muted-foreground">
              <tr>
                <th className="px-4 py-2 font-medium">Description</th>
                <th className="px-4 py-2 text-right font-medium">Qté</th>
                <th className="px-4 py-2 text-right font-medium">PU HT</th>
                <th className="px-4 py-2 text-right font-medium">TVA</th>
                <th className="px-4 py-2 text-right font-medium">Total TTC</th>
              </tr>
            </thead>
            <tbody>
              {invoice.lines.map((line) => (
                <tr key={line.id} className="border-t border-border">
                  <td className="px-4 py-2 text-foreground">
                    {line.description}
                  </td>
                  <td className="px-4 py-2 text-right tabular-nums text-foreground">
                    {line.quantity}
                  </td>
                  <td className="px-4 py-2 text-right tabular-nums text-foreground">
                    {formatAmount(line.unit_price_ht, invoice.currency)}
                  </td>
                  <td className="px-4 py-2 text-right tabular-nums text-foreground">
                    {Number(line.vat_rate) * 100}&nbsp;%
                  </td>
                  <td className="px-4 py-2 text-right tabular-nums text-foreground">
                    {formatAmount(line.line_amount_ttc, invoice.currency)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="rounded-md border border-primary bg-primary/5 px-4 py-3">
        <p className="text-sm font-medium text-foreground">
          Total HT :{' '}
          <span className="tabular-nums">
            {formatAmount(invoice.total_amount_ht, invoice.currency)}
          </span>
        </p>
        <p className="text-sm font-medium text-foreground">
          Total TTC :{' '}
          <span className="tabular-nums">
            {formatAmount(invoice.total_amount_ttc, invoice.currency)}
          </span>
        </p>
      </div>

      <div className="flex flex-col gap-3">
        <h2 className="text-sm font-semibold text-foreground">Conformité</h2>

        {invoice.status === 'ANALYSIS_STALE' ? (
          <div role="status" className="rounded-md border border-warning bg-warning/10 px-3 py-2 text-sm text-warning">
            Cette facture a été modifiée depuis sa dernière analyse : le résultat ci-dessous ne
            reflète plus son état actuel. Relancez l&apos;analyse pour le mettre à jour.
          </div>
        ) : null}

        {triggerState.status === 'business-error' ? (
          <p role="alert" className="rounded-md border border-info bg-info/10 px-3 py-2 text-sm text-info">
            {triggerState.message}{' '}
            <Link href="/company" className="font-medium underline">
              Configurer mon entreprise
            </Link>
          </p>
        ) : null}

        {triggerState.status === 'technical-error' ? (
          <p role="alert" className="rounded-md border border-warning bg-warning/10 px-3 py-2 text-sm text-warning">
            {triggerState.message}
          </p>
        ) : null}

        {analysis ? (
          <ComplianceResultSummary
            analysis={analysis}
            context={{ customerId: invoice.customer_id, invoiceId: invoice.id }}
          />
        ) : (
          <p className="text-sm text-muted-foreground">Cette facture n&apos;a pas encore été analysée.</p>
        )}

        {invoice.status !== 'DRAFT' ? (
          <Button
            type="button"
            variant={analysis ? 'secondary' : 'primary'}
            loading={triggerState.status === 'loading'}
            onClick={handleTriggerAnalysis}
            className="w-fit"
          >
            {analysis ? "Relancer l'analyse" : "Lancer l'analyse"}
          </Button>
        ) : null}
      </div>
    </div>
  );
}
