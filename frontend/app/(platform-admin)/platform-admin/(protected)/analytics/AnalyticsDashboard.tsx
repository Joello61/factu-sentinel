"use client";

import { useEffect, useState } from "react";
import { Building2, CheckCircle2, ClipboardCheck, Users } from "lucide-react";
import {
  Bar,
  CartesianGrid,
  ComposedChart,
  Legend,
  Line,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { platformAdminApiRequest } from "@/lib/api/platformAdminClient";
import type {
  PlatformAnalyticsSummary,
  PlatformAnalyticsTrendPoint,
  PlatformAnalyticsTrends,
} from "@/lib/api/platformAdminTypes";
import { StatTile } from "@/components/platform-admin/StatTile";

type ViewState =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; summary: PlatformAnalyticsSummary; points: PlatformAnalyticsTrendPoint[] };

const DATE_FORMATTER = new Intl.DateTimeFormat("fr-FR", { day: "2-digit", month: "2-digit" });

function formatDate(isoDate: string): string {
  return DATE_FORMATTER.format(new Date(`${isoDate}T00:00:00Z`));
}

function formatPercent(rate: string): string {
  return `${(Number(rate) * 100).toFixed(1)} %`;
}

/**
 * US-ANALYTICS-001/002 (docs/08-api-specification.md, section 38.3). Résumé cumulé + deux
 * graphiques d'évolution sur la fenêtre fixe de 90 jours (UTC) renvoyée par l'API - premiers
 * graphiques du produit (docs/11-frontend-design-system.md, section 48), jamais sur le
 * dashboard utilisateur final (DL-008/DL-015, périmètre inchangé).
 */
export function AnalyticsDashboard() {
  const [state, setState] = useState<ViewState>({ status: "loading" });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const [summary, trends] = await Promise.all([
          platformAdminApiRequest<PlatformAnalyticsSummary>("/api/v1/platform-admin/analytics/summary"),
          platformAdminApiRequest<PlatformAnalyticsTrends>("/api/v1/platform-admin/analytics/trends"),
        ]);
        if (!cancelled) {
          setState({ status: "ready", summary, points: trends.points });
        }
      } catch {
        if (!cancelled) {
          setState({ status: "error", message: "Impossible de charger les statistiques d'usage pour le moment." });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="flex flex-col gap-6">
      <div>
        <h1 className="text-2xl font-semibold text-foreground">Statistiques d&apos;usage</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Vue agrégée en lecture seule de l&apos;usage réel du produit, tous tenants confondus.
        </p>
      </div>

      {state.status === "loading" ? <p className="text-sm text-muted-foreground">Chargement…</p> : null}

      {state.status === "error" ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {state.message}
        </p>
      ) : null}

      {state.status === "ready" ? (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatTile
              icon={Building2}
              tone="info"
              label="Organisations"
              value={String(state.summary.organizations_count)}
            />
            <StatTile icon={Users} tone="info" label="Utilisateurs" value={String(state.summary.users_count)} />
            <StatTile
              icon={ClipboardCheck}
              tone="info"
              label="Analyses de conformité complétées"
              value={String(state.summary.compliance_analyses_count)}
            />
            <StatTile
              icon={CheckCircle2}
              tone="success"
              label="Taux de conformité"
              value={formatPercent(state.summary.compliance_rate)}
            />
          </div>

          <GrowthChart points={state.points} />
          <ComplianceActivityChart points={state.points} />
        </>
      ) : null}
    </div>
  );
}

function hasAnyData(points: PlatformAnalyticsTrendPoint[], keys: ("organizations_created" | "users_created" | "compliance_analyses_count")[]): boolean {
  return points.some((point) => keys.some((key) => point[key] > 0));
}

function GrowthChart({ points }: { points: PlatformAnalyticsTrendPoint[] }) {
  const hasData = hasAnyData(points, ["organizations_created", "users_created"]);

  return (
    <section aria-labelledby="analytics-growth-heading" className="rounded-md border border-border p-4">
      <h2 id="analytics-growth-heading" className="text-base font-semibold text-foreground">
        Croissance (90 derniers jours)
      </h2>
      <p className="mt-1 text-xs text-muted-foreground">Nouvelles organisations et nouveaux utilisateurs par jour.</p>

      {hasData ? (
        <div
          className="mt-4 h-64 w-full"
          role="img"
          aria-label="Graphique d'évolution du nombre d'organisations et d'utilisateurs créés par jour sur les 90 derniers jours."
        >
          <ResponsiveContainer width="100%" height="100%">
            <ComposedChart accessibilityLayer data={points} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" />
              <XAxis dataKey="date" tickFormatter={formatDate} stroke="var(--color-muted-foreground)" fontSize={12} minTickGap={24} />
              <YAxis allowDecimals={false} stroke="var(--color-muted-foreground)" fontSize={12} />
              <Tooltip labelFormatter={(label) => formatDate(String(label))} contentStyle={{ fontSize: 12 }} />
              <Legend />
              <Line
                type="monotone"
                dataKey="organizations_created"
                name="Organisations"
                stroke="var(--color-primary)"
                dot={false}
                strokeWidth={2}
              />
              <Line
                type="monotone"
                dataKey="users_created"
                name="Utilisateurs"
                stroke="var(--color-info)"
                dot={false}
                strokeWidth={2}
              />
            </ComposedChart>
          </ResponsiveContainer>
        </div>
      ) : (
        <p className="mt-4 text-sm text-muted-foreground">
          Aucune nouvelle organisation ni aucun nouvel utilisateur sur les 90 derniers jours.
        </p>
      )}

      <TrendDataTable
        caption="Organisations et utilisateurs créés par jour, 90 derniers jours"
        points={points}
        columns={[
          { key: "organizations_created", label: "Organisations créées" },
          { key: "users_created", label: "Utilisateurs créés" },
        ]}
      />
    </section>
  );
}

function ComplianceActivityChart({ points }: { points: PlatformAnalyticsTrendPoint[] }) {
  const hasData = hasAnyData(points, ["compliance_analyses_count"]);

  return (
    <section aria-labelledby="analytics-compliance-heading" className="rounded-md border border-border p-4">
      <h2 id="analytics-compliance-heading" className="text-base font-semibold text-foreground">
        Analyses de conformité (90 derniers jours)
      </h2>
      <p className="mt-1 text-xs text-muted-foreground">
        Volume d&apos;analyses complétées et taux de conformité par jour.
      </p>

      {hasData ? (
        <div
          className="mt-4 h-64 w-full"
          role="img"
          aria-label="Graphique du volume d'analyses de conformité et du taux de conformité par jour sur les 90 derniers jours."
        >
          <ResponsiveContainer width="100%" height="100%">
            <ComposedChart accessibilityLayer data={points} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" />
              <XAxis dataKey="date" tickFormatter={formatDate} stroke="var(--color-muted-foreground)" fontSize={12} minTickGap={24} />
              <YAxis yAxisId="count" allowDecimals={false} stroke="var(--color-muted-foreground)" fontSize={12} />
              <YAxis
                yAxisId="rate"
                orientation="right"
                domain={[0, 1]}
                tickFormatter={(value: number) => `${Math.round(value * 100)} %`}
                stroke="var(--color-muted-foreground)"
                fontSize={12}
              />
              <Tooltip
                labelFormatter={(label) => formatDate(String(label))}
                formatter={(value, name) =>
                  "Taux de conformité" === name ? [`${(Number(value) * 100).toFixed(1)} %`, name] : [value, name]
                }
                contentStyle={{ fontSize: 12 }}
              />
              <Legend />
              <Bar
                yAxisId="count"
                dataKey="compliance_analyses_count"
                name="Analyses"
                fill="var(--color-primary)"
                fillOpacity={0.6}
              />
              <Line
                yAxisId="rate"
                type="monotone"
                dataKey={(point: PlatformAnalyticsTrendPoint) => Number(point.compliance_rate)}
                name="Taux de conformité"
                stroke="var(--color-success)"
                dot={false}
                strokeWidth={2}
              />
            </ComposedChart>
          </ResponsiveContainer>
        </div>
      ) : (
        <p className="mt-4 text-sm text-muted-foreground">
          Aucune analyse de conformité complétée sur les 90 derniers jours.
        </p>
      )}

      <TrendDataTable
        caption="Analyses de conformité et taux de conformité par jour, 90 derniers jours"
        points={points}
        columns={[
          { key: "compliance_analyses_count", label: "Analyses" },
          { key: "compliance_rate", label: "Taux de conformité", format: formatPercent },
        ]}
      />
    </section>
  );
}

/**
 * Alternative textuelle pour lecteur d'écran (docs/11-frontend-design-system.md, section 48) -
 * visuellement masquée (sr-only) mais toujours dans le DOM : indépendante des limites
 * d'accessibilité clavier propres à une bibliothèque de graphiques, garantit que chaque
 * donnée du graphique reste consultable sans dépendre du rendu SVG.
 */
function TrendDataTable({
  caption,
  points,
  columns,
}: {
  caption: string;
  points: PlatformAnalyticsTrendPoint[];
  columns: { key: Exclude<keyof PlatformAnalyticsTrendPoint, "date">; label: string; format?: (raw: string) => string }[];
}) {
  return (
    <table className="sr-only">
      <caption>{caption}</caption>
      <thead>
        <tr>
          <th scope="col">Date</th>
          {columns.map((column) => (
            <th scope="col" key={column.key}>
              {column.label}
            </th>
          ))}
        </tr>
      </thead>
      <tbody>
        {points.map((point) => (
          <tr key={point.date}>
            <th scope="row">{formatDate(point.date)}</th>
            {columns.map((column) => (
              <td key={column.key}>{column.format ? column.format(String(point[column.key])) : String(point[column.key])}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}
