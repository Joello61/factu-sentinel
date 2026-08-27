'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import { apiRequest, ApiError } from '@/lib/api/client';
import { ComplianceResultSummary } from '@/components/compliance/ComplianceResultSummary';
import { formatTimestamp } from '@/lib/format/date';
import type { ComplianceAnalysis, Invoice } from '@/lib/api/types';

type ViewState =
  | { status: 'loading' }
  | { status: 'not-found' }
  | { status: 'error'; message: string }
  | { status: 'ready'; analysis: ComplianceAnalysis; invoice: Invoice };

/**
 * Détail d'une analyse passée (docs/05-user-stories.md, US-HISTORY-001 : « pourquoi cette
 * facture était-elle considérée comme non conforme le [date] ? ») -- distinct de
 * InvoiceDetail, qui n'affiche jamais que la dernière analyse d'une facture
 * (../../invoices/InvoiceDetail.tsx). Lecture seule : jamais de bouton de relance d'analyse
 * ici, cette action appartient à la page facture.
 */
export function ComplianceAnalysisDetail({ analysisId }: { analysisId: string }) {
  const [state, setState] = useState<ViewState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const analysis = await apiRequest<ComplianceAnalysis>(`/api/v1/compliance-analyses/${analysisId}`);
        const invoice = await apiRequest<Invoice>(`/api/v1/invoices/${analysis.invoice_id}`);
        if (!cancelled) {
          setState({ status: 'ready', analysis, invoice });
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
          message: 'Impossible de charger cette analyse pour le moment.',
        });
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [analysisId]);

  if (state.status === 'loading') {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  if (state.status === 'not-found') {
    return (
      <p className="text-sm text-muted-foreground">
        Cette analyse n&apos;existe pas ou n&apos;est plus disponible.
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

  const { analysis, invoice } = state;

  return (
    <div className="flex max-w-3xl flex-col gap-6">
      <div>
        <Link href="/history" className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
          <ArrowLeft aria-hidden="true" size={12} strokeWidth={2} />
          Retour à l&apos;historique
        </Link>
        <div className="mt-2 flex flex-wrap items-center gap-3">
          <h1 className="text-2xl font-semibold text-foreground">
            {invoice.invoice_number ?? 'Facture sans numéro'}
          </h1>
        </div>
        <p className="mt-1 text-sm text-muted-foreground">
          Analyse du {formatTimestamp(analysis.triggered_at)}
        </p>
      </div>

      <div role="status" className="rounded-md border border-info bg-info/10 px-3 py-2 text-sm text-info">
        Ceci est une analyse passée. La facture a peut-être été modifiée ou réanalysée depuis.{' '}
        <Link href={`/invoices/${invoice.id}`} className="font-medium underline">
          Voir la facture actuelle
        </Link>
      </div>

      <ComplianceResultSummary
        analysis={analysis}
        context={{ customerId: invoice.customer_id, invoiceId: invoice.id }}
      />
    </div>
  );
}
