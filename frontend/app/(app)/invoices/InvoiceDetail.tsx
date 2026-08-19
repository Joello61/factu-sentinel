'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiRequest, ApiError } from '@/lib/api/client';
import { InvoiceStatusBadge } from '@/components/ui/InvoiceStatusBadge';
import { formatBusinessDate } from '@/lib/format/date';
import { formatAmount } from '@/lib/format/amount';
import type { Customer, Invoice } from '@/lib/api/types';

type ViewState =
  | { status: 'loading' }
  | { status: 'not-found' }
  | { status: 'error'; message: string }
  | { status: 'ready'; invoice: Invoice; customer: Customer | null };

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
 * Détail d'une facture (docs/11-frontend-design-system.md, section 32) : lignes, client
 * (avec son type, jamais caché), statut d'analyse. Pas de résultat de conformité affiché ici
 * : le Compliance Engine (Phase 5-6) n'existe pas encore à ce stade du produit.
 */
export function InvoiceDetail({ invoiceId }: { invoiceId: string }) {
  const [state, setState] = useState<ViewState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const invoice = await apiRequest<Invoice>(
          `/api/v1/invoices/${invoiceId}`,
        );
        let customer: Customer | null = null;
        try {
          customer = await apiRequest<Customer>(
            `/api/v1/customers/${invoice.customer_id}`,
          );
        } catch {
          customer = null;
        }
        if (!cancelled) {
          setState({ status: 'ready', invoice, customer });
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
      <p
        role="alert"
        className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error"
      >
        {state.message}
      </p>
    );
  }

  const { invoice, customer } = state;

  return (
    <div className="flex max-w-3xl flex-col gap-6">
      <div>
        <Link
          href="/invoices"
          className="text-xs font-medium text-primary hover:underline"
        >
          ← Retour aux factures
        </Link>
        <div className="mt-2 flex items-center gap-3">
          <h1 className="text-2xl font-semibold text-foreground">
            {invoice.invoice_number ?? 'Facture sans numéro'}
          </h1>
          <InvoiceStatusBadge status={invoice.status} />
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
    </div>
  );
}
