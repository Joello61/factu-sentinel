import { useEffect } from "react";
import { apiRequest } from "@/lib/api/client";
import type { DocumentFile } from "@/lib/api/types";

const POLL_INTERVAL_MS = 3000;
const POLL_TIMEOUT_MS = 120_000;

const TERMINAL_STATUSES: DocumentFile["processing_status"][] = ["VALIDATED", "FAILED"];

/**
 * Polling borné du statut d'un document en cours de traitement (../../CLAUDE.md frontend,
 * section 7 : jamais de WebSocket au MVP). Bornes explicites (plan Phase 7, correction
 * demandée en revue) : intervalle fixe, arrêt immédiat sur état terminal, timeout maximal
 * (au-delà, l'appelant reste sur le dernier état connu plutôt qu'un polling indéfini si
 * Mustang est durablement indisponible), nettoyage systématique au démontage.
 */
export function useDocumentPolling(documentId: string | null, onUpdate: (document: DocumentFile) => void): void {
  useEffect(() => {
    if (null === documentId) {
      return;
    }

    let cancelled = false;
    let timeoutHandle: ReturnType<typeof setTimeout> | undefined;
    let elapsedMs = 0;

    async function poll() {
      if (cancelled) {
        return;
      }

      try {
        const document = await apiRequest<DocumentFile>(`/api/v1/documents/${documentId}`);
        if (cancelled) {
          return;
        }
        onUpdate(document);
        if (TERMINAL_STATUSES.includes(document.processing_status)) {
          return;
        }
      } catch {
        // Erreur réseau ponctuelle : on retente jusqu'au timeout, jamais un arrêt immédiat.
      }

      elapsedMs += POLL_INTERVAL_MS;
      if (elapsedMs >= POLL_TIMEOUT_MS) {
        return;
      }

      timeoutHandle = setTimeout(poll, POLL_INTERVAL_MS);
    }

    timeoutHandle = setTimeout(poll, POLL_INTERVAL_MS);

    return () => {
      cancelled = true;
      clearTimeout(timeoutHandle);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- onUpdate est recréée à chaque rendu du parent, l'inclure redémarrerait le polling en boucle.
  }, [documentId]);
}
