"use client";

import { useEffect, useState } from "react";
import { AlertTriangle, Bot, CheckCircle2, Mailbox } from "lucide-react";
import { platformAdminApiRequest } from "@/lib/api/platformAdminClient";
import type { PlatformHealth } from "@/lib/api/platformAdminTypes";

type ViewState =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; health: PlatformHealth };

/**
 * US-PLATFORMADMIN-005 (docs/08-api-specification.md, section 38.2). Explicitement limité au
 * niveau applicatif - aucun indicateur d'infrastructure réelle (Phase 17).
 */
export function HealthDashboard() {
  const [state, setState] = useState<ViewState>({ status: "loading" });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const health = await platformAdminApiRequest<PlatformHealth>("/api/v1/platform-admin/health");
        if (!cancelled) {
          setState({ status: "ready", health });
        }
      } catch {
        if (!cancelled) {
          setState({ status: "error", message: "Impossible de charger la santé applicative pour le moment." });
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
        <h1 className="text-2xl font-semibold text-foreground">Santé applicative</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Indicateurs applicatifs uniquement - le suivi d&apos;infrastructure reste hors périmètre.
        </p>
      </div>

      {state.status === "loading" ? <p className="text-sm text-muted-foreground">Chargement…</p> : null}

      {state.status === "error" ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {state.message}
        </p>
      ) : null}

      {state.status === "ready" ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <StatTile
            icon={state.health.api_health === "ok" ? CheckCircle2 : AlertTriangle}
            tone={state.health.api_health === "ok" ? "success" : "error"}
            label="Statut API"
            value={state.health.api_health === "ok" ? "Opérationnel" : "Dégradé"}
          />
          <StatTile
            icon={AlertTriangle}
            tone={Number(state.health.compliance_engine_failure_rate_24h) > 0 ? "warning" : "success"}
            label="Taux d'échec du moteur de conformité (24h)"
            value={`${(Number(state.health.compliance_engine_failure_rate_24h) * 100).toFixed(1)} %`}
          />
          <StatTile
            icon={Mailbox}
            tone={state.health.async_jobs_dead_letter_count > 0 ? "warning" : "success"}
            label="Jobs asynchrones en échec définitif"
            value={String(state.health.async_jobs_dead_letter_count)}
          />
          <StatTile
            icon={Bot}
            tone="info"
            label="Appels IA (24h)"
            value={`${state.health.ai_calls_volume_24h} · ${state.health.ai_estimated_cost_24h} €`}
          />
        </div>
      ) : null}
    </div>
  );
}

const TONE_CLASSES: Record<"success" | "warning" | "error" | "info", string> = {
  success: "border-success/40 bg-success/10 text-success",
  warning: "border-warning/40 bg-warning/10 text-warning",
  error: "border-error/40 bg-error/10 text-error",
  info: "border-info/40 bg-info/10 text-info",
};

function StatTile({
  icon: Icon,
  tone,
  label,
  value,
}: {
  icon: typeof CheckCircle2;
  tone: "success" | "warning" | "error" | "info";
  label: string;
  value: string;
}) {
  return (
    <div className={`flex flex-col gap-2 rounded-md border p-4 ${TONE_CLASSES[tone]}`}>
      <Icon aria-hidden="true" size={20} strokeWidth={1.75} />
      <span className="text-2xl font-semibold tabular-nums">{value}</span>
      <span className="text-xs font-medium">{label}</span>
    </div>
  );
}
