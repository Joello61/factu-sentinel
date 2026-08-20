'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiRequest, apiRequestPaginated, apiRequestUpload, ApiError } from '@/lib/api/client';
import { InvoiceStatusBadge } from '@/components/ui/InvoiceStatusBadge';
import { DocumentStatusBadge } from '@/components/ui/DocumentStatusBadge';
import { FileUpload } from '@/components/ui/FileUpload';
import { Button } from '@/components/ui/Button';
import { ComplianceResultSummary } from '@/components/compliance/ComplianceResultSummary';
import { AssistantQuestionForm } from '@/components/compliance/AssistantQuestionForm';
import { formatBusinessDate } from '@/lib/format/date';
import { formatAmount } from '@/lib/format/amount';
import { useDocumentPolling } from '@/lib/hooks/useDocumentPolling';
import type {
  Customer,
  Invoice,
  DocumentFile,
  DocumentProcessingFailureReason,
  ComplianceAnalysis,
  ComplianceAnalysisSummary,
} from '@/lib/api/types';

/**
 * FORMAT_NOT_SUPPORTED n'est jamais un jugement sur le fichier, contrairement aux autres
 * valeurs (docs/08-api-specification.md, section 31 ; ../../../CLAUDE.md frontend, section
 * 7) - messages distincts, jamais un texte générique unique pour "FAILED".
 */
const FAILURE_REASON_MESSAGES: Record<DocumentProcessingFailureReason, string> = {
  FORMAT_NOT_SUPPORTED:
    "Ce format (UBL/CII) n'est pas encore pris en charge par FactuSentinel. Le fichier n'est pas nécessairement invalide - utilisez la saisie manuelle en complément.",
  MUSTANG_UNAVAILABLE: 'Le traitement est temporairement indisponible. Réessayez dans quelques instants.',
  MUSTANG_VALIDATION_FAILED: "Le document n'a pas pu être validé techniquement.",
  INVALID_DOCUMENT: "Le fichier n'a pas pu être lu. Vérifiez qu'il n'est pas corrompu.",
  SECURITY_REJECTED: "Le fichier a été rejeté lors de la validation de sécurité.",
};

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
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  // Un seul document suivi à la fois (un dropzone -> un upload à la fois) : voir
  // frontend/lib/hooks/useDocumentPolling.ts pour les bornes du polling lui-même.
  const [pollingDocumentId, setPollingDocumentId] = useState<string | null>(null);

  useDocumentPolling(pollingDocumentId, (updated) => {
    setState((current) => {
      if ('ready' !== current.status) {
        return current;
      }
      return {
        ...current,
        invoice: {
          ...current.invoice,
          documents: current.invoice.documents.map((document) => (document.id === updated.id ? updated : document)),
        },
      };
    });
    if ('VALIDATED' === updated.processing_status || 'FAILED' === updated.processing_status) {
      setPollingDocumentId(null);
    }
  });

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

  async function handleFileUpload(file: File) {
    setUploading(true);
    setUploadError(null);

    const formData = new FormData();
    formData.append('invoice_id', invoiceId);
    formData.append('file', file);

    try {
      const document = await apiRequestUpload<DocumentFile>('/api/v1/documents', formData, {
        // Une clé fraîche par upload : chaque dépôt de fichier est une intention distincte
        // (même principe que handleTriggerAnalysis ci-dessous).
        'Idempotency-Key': crypto.randomUUID(),
      });

      setState((current) =>
        'ready' !== current.status
          ? current
          : { ...current, invoice: { ...current.invoice, documents: [document, ...current.invoice.documents] } },
      );
      setPollingDocumentId(document.id);
    } catch (error) {
      setUploadError(
        error instanceof ApiError
          ? error.message
          : "Le document n'a pas pu être importé pour le moment. Réessayez.",
      );
    } finally {
      setUploading(false);
    }
  }

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
        <h2 className="text-sm font-semibold text-foreground">Documents</h2>

        {invoice.documents.length > 0 ? (
          <ul className="flex flex-col gap-2">
            {invoice.documents.map((document) => (
              <li key={document.id} className="rounded-md border border-border p-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <span className="text-sm font-medium text-foreground">{document.file_name}</span>
                  <DocumentStatusBadge status={document.processing_status} />
                </div>
                {document.failure_reason ? (
                  <p
                    role="alert"
                    className={`mt-2 text-xs ${
                      'FORMAT_NOT_SUPPORTED' === document.failure_reason ? 'text-muted-foreground' : 'text-warning'
                    }`}
                  >
                    {FAILURE_REASON_MESSAGES[document.failure_reason]}
                  </p>
                ) : null}
                {document.suggestions ? (
                  <div className="mt-2 rounded-md bg-info/10 px-3 py-2 text-xs text-foreground">
                    <p className="font-medium text-info">Suggestions extraites du document</p>
                    <p className="mt-1 text-muted-foreground">
                      À vérifier et confirmer vous-même dans les champs ci-dessus - ces valeurs ne sont jamais
                      enregistrées automatiquement.
                    </p>
                    <dl className="mt-1.5 flex flex-col gap-0.5">
                      {Object.entries(document.suggestions).map(([key, value]) => (
                        <div key={key} className="flex gap-1.5">
                          <dt className="text-muted-foreground">{key} :</dt>
                          <dd className="text-foreground">{value}</dd>
                        </div>
                      ))}
                    </dl>
                  </div>
                ) : null}
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-muted-foreground">Aucun document importé pour cette facture.</p>
        )}

        {invoice.status === 'DRAFT' || invoice.status === 'READY_FOR_ANALYSIS' ? (
          <FileUpload onFileSelected={handleFileUpload} disabled={uploading} error={uploadError} />
        ) : (
          <p className="text-xs text-muted-foreground">
            Cette facture a déjà été analysée : l&apos;import de document n&apos;est plus disponible.
          </p>
        )}
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

        <AssistantQuestionForm />

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
