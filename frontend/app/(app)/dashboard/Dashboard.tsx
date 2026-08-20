'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { apiRequest } from '@/lib/api/client';
import { DashboardStatusBadge } from '@/components/ui/DashboardStatusBadge';
import { ComplianceResultBadge } from '@/components/ui/ComplianceResultBadge';
import { formatTimestamp } from '@/lib/format/date';
import type { Dashboard as DashboardData } from '@/lib/api/types';

type ViewState =
  | { status: 'loading' }
  | { status: 'error'; message: string }
  | { status: 'ready'; dashboard: DashboardData };

/**
 * Dashboard (docs/11-frontend-design-system.md, section 34 ; US-DASHBOARD-001) : répond aux
 * quatre questions du design system (état global, problèmes non résolus, avertissements en
 * cours, actions recommandées) -- jamais un dashboard de statistiques génériques sans lien
 * direct avec la conformité (section 34, "à éviter").
 */
export function Dashboard() {
  const [state, setState] = useState<ViewState>({ status: 'loading' });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const dashboard = await apiRequest<DashboardData>('/api/v1/dashboard');
        if (!cancelled) {
          setState({ status: 'ready', dashboard });
        }
      } catch {
        if (!cancelled) {
          setState({
            status: 'error',
            message: 'Impossible de charger le dashboard pour le moment.',
          });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  if (state.status === 'loading') {
    return <p className="text-sm text-muted-foreground">Chargement…</p>;
  }

  if (state.status === 'error') {
    return (
      <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
        {state.message}
      </p>
    );
  }

  const { dashboard } = state;

  // US-DASHBOARD-001 : « Given je n'ai encore effectué aucune analyse, When j'accède au
  // dashboard, Then le système m'oriente vers le diagnostic d'éligibilité ou l'analyse d'une
  // première facture » -- jamais un état vide sans action proposée.
  if (dashboard.global_status === 'AUCUNE_ANALYSE') {
    return (
      <div className="flex flex-col gap-6">
        <h1 className="text-2xl font-semibold text-foreground">Dashboard</h1>
        <div className="rounded-md border border-border p-6 text-center">
          <p className="text-sm text-foreground">
            Vous n&apos;avez encore effectué aucune analyse de conformité.
          </p>
          <div className="mt-4 flex flex-wrap items-center justify-center gap-3">
            <Link
              href="/diagnostic"
              className="inline-flex items-center justify-center rounded-md border border-primary px-4 py-2 text-sm font-medium text-primary hover:bg-primary/5"
            >
              Comprendre mon calendrier
            </Link>
            <Link
              href="/invoices/new"
              className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
            >
              Analyser une première facture
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const noIssuesAtAll = 0 === dashboard.open_issues_count && 0 === dashboard.warnings_count;

  return (
    <div className="flex flex-col gap-8">
      <div className="flex flex-wrap items-center gap-3">
        <h1 className="text-2xl font-semibold text-foreground">Dashboard</h1>
        <DashboardStatusBadge status={dashboard.global_status} />
      </div>

      {noIssuesAtAll ? (
        <p className="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
          Aucun problème détecté sur vos dernières analyses.
        </p>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="rounded-md border border-border p-4">
            <p className="text-xs font-medium text-muted-foreground">Problèmes non résolus</p>
            <p className="mt-1 text-3xl font-semibold tabular-nums text-error">{dashboard.open_issues_count}</p>
          </div>
          <div className="rounded-md border border-border p-4">
            <p className="text-xs font-medium text-muted-foreground">Avertissements en cours</p>
            <p className="mt-1 text-3xl font-semibold tabular-nums text-warning">{dashboard.warnings_count}</p>
          </div>
        </div>
      )}

      {dashboard.recommended_actions.length > 0 ? (
        <div className="flex flex-col gap-3">
          <h2 className="text-sm font-semibold text-foreground">Actions recommandées</h2>
          <ul className="flex flex-col gap-2">
            {dashboard.recommended_actions.map((action, index) => (
              <li
                key={`${action.related_analysis_id}-${index}`}
                className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-surface px-3 py-2"
              >
                <span className="text-sm text-foreground">{action.message}</span>
                <Link
                  href={`/history/${action.related_analysis_id}`}
                  className="text-sm font-medium text-primary hover:underline"
                >
                  Consulter
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {dashboard.recent_analyses.length > 0 ? (
        <div className="flex flex-col gap-3">
          <h2 className="text-sm font-semibold text-foreground">Analyses récentes</h2>
          <ul className="flex flex-col gap-2">
            {dashboard.recent_analyses.map((analysis) => (
              <li
                key={analysis.id}
                className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2"
              >
                <div className="flex items-center gap-3">
                  {analysis.global_result ? <ComplianceResultBadge result={analysis.global_result} /> : null}
                  <span className="text-xs text-muted-foreground">{formatTimestamp(analysis.triggered_at)}</span>
                </div>
                <Link href={`/history/${analysis.id}`} className="text-sm font-medium text-primary hover:underline">
                  Consulter
                </Link>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  );
}
