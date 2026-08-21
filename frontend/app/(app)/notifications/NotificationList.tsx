"use client";

import { useEffect, useState } from "react";
import { Bell } from "lucide-react";
import { apiRequest, apiRequestPaginated } from "@/lib/api/client";
import { formatTimestamp } from "@/lib/format/date";
import type { AppNotification } from "@/lib/api/types";

type State =
  | { status: "loading" }
  | { status: "error"; message: string }
  | { status: "ready"; notifications: AppNotification[] };

/**
 * Centre de notifications (US-NOTIFICATION-001/003, docs/11-frontend-design-system.md
 * section 59). GET /notifications est déjà filtré par destinataire côté backend
 * (App\Notification\Repository\NotificationRepository) - jamais de filtrage supplémentaire
 * nécessaire ici.
 *
 * Endpoint paginé (docs/08-api-specification.md, section 34) : une seule page volontairement
 * large (per_page=50) plutôt qu'une pagination cliquable, même choix que
 * app/(app)/customers/CustomerList.tsx pour une liste au volume attendu modeste à cette
 * échelle (persona primaire, `03-market-analysis.md`) - à revoir si un usage réel dépasse ce
 * volume.
 */
export function NotificationList() {
  const [state, setState] = useState<State>({ status: "loading" });

  useEffect(() => {
    let cancelled = false;

    (async () => {
      try {
        const { data: notifications } = await apiRequestPaginated<AppNotification>(
          "/api/v1/notifications?per_page=50",
        );
        if (!cancelled) {
          setState({ status: "ready", notifications });
        }
      } catch {
        if (!cancelled) {
          setState({ status: "error", message: "Impossible de charger vos notifications pour le moment." });
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  async function handleMarkRead(notification: AppNotification) {
    if (state.status !== "ready" || notification.read_at !== null) {
      return;
    }
    try {
      const updated = await apiRequest<AppNotification>(`/api/v1/notifications/${notification.id}/read`, {
        method: "PATCH",
      });
      setState({
        status: "ready",
        notifications: state.notifications.map((item) => (item.id === updated.id ? updated : item)),
      });
    } catch {
      // Optimistic UI non retenue pour cette action non plus (docs/11-frontend-design-system.md,
      // section 36) : en cas d'échec, la notification reste simplement non lue - action à
      // faible enjeu, l'utilisateur peut réessayer.
    }
  }

  return (
    <div className="flex max-w-2xl flex-col gap-6">
      <h1 className="text-2xl font-semibold text-foreground">Notifications</h1>

      {state.status === "loading" ? <p className="text-sm text-muted-foreground">Chargement…</p> : null}

      {state.status === "error" ? (
        <p role="alert" className="rounded-md border border-error bg-error/10 px-3 py-2 text-sm text-error">
          {state.message}
        </p>
      ) : null}

      {state.status === "ready" && state.notifications.length === 0 ? (
        <p className="text-sm text-muted-foreground">Aucune notification pour le moment.</p>
      ) : null}

      {state.status === "ready" && state.notifications.length > 0 ? (
        <ul className="flex flex-col gap-2">
          {state.notifications.map((notification) => (
            <li key={notification.id}>
              <button
                type="button"
                onClick={() => void handleMarkRead(notification)}
                className={`flex w-full flex-col gap-1 rounded-md border p-4 text-left ${
                  notification.read_at === null
                    ? "border-primary/40 bg-primary/5"
                    : "border-border bg-surface"
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <span className="flex items-start gap-2 text-sm text-foreground">
                    <Bell aria-hidden="true" className="mt-0.5 shrink-0 text-primary" size={16} strokeWidth={1.75} />
                    {notification.message}
                  </span>
                  {notification.read_at === null ? (
                    <span className="shrink-0 rounded-full bg-primary px-2 py-0.5 text-[10px] font-medium text-white">
                      Non lue
                    </span>
                  ) : null}
                </div>
                <span className="pl-6 text-xs text-muted-foreground">
                  {formatTimestamp(notification.scheduled_for)}
                </span>
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
