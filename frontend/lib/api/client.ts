import { ApiError, isApiErrorBody } from "./types";

/**
 * Client API centralisé (../CLAUDE.md, section 4) : aucun fetch brut ne doit être dispersé
 * dans les composants. L'AuthProvider (components/auth/AuthProvider.tsx) est le seul
 * appelant de configureApiClient : il détient l'access token en mémoire et la fonction de
 * refresh single-flight, jamais dupliqués ici.
 */
interface ApiClientConfig {
  getAccessToken: () => string | null;
  refreshAccessToken: () => Promise<string | null>;
}

let config: ApiClientConfig = {
  getAccessToken: () => null,
  refreshAccessToken: async () => null,
};

export function configureApiClient(next: ApiClientConfig): void {
  config = next;
}

interface ApiRequestInit extends Omit<RequestInit, "body"> {
  body?: unknown;
  /** Ajoute l'en-tête Authorization et tente un refresh sur 401. Défaut : true. */
  auth?: boolean;
}

async function performRequest(path: string, init: ApiRequestInit): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set("Content-Type", "application/json");

  if (init.auth !== false) {
    const token = config.getAccessToken();
    if (token) {
      headers.set("Authorization", `Bearer ${token}`);
    }
  }

  return fetch(path, {
    ...init,
    headers,
    // Le cookie HttpOnly de refresh doit être transmis sur /auth/refresh et /auth/logout,
    // même en same-origin (comportement par défaut du navigateur, explicité ici).
    credentials: "include",
    body: init.body !== undefined ? JSON.stringify(init.body) : undefined,
  });
}

async function parseEnvelope<T>(response: Response): Promise<T> {
  const raw = await response.text();
  const body: unknown = raw.length > 0 ? JSON.parse(raw) : null;

  if (!response.ok) {
    if (isApiErrorBody(body)) {
      throw new ApiError(response.status, body);
    }
    throw new ApiError(response.status, {
      error: { code: "UNKNOWN_ERROR", message: "Une erreur inattendue est survenue.", details: [], request_id: null },
    });
  }

  return (body as { data: T }).data;
}

/**
 * Point d'entrée unique pour appeler l'API backend. Sur une réponse 401 avec `auth` actif,
 * tente exactement un refresh (délégué à AuthProvider, qui garantit le single-flight) puis
 * rejoue la requête une seule fois — jamais de boucle de retry.
 */
export async function apiRequest<T>(path: string, init: ApiRequestInit = {}): Promise<T> {
  const response = await performRequest(path, init);

  if (response.status === 401 && init.auth !== false) {
    const token = await config.refreshAccessToken();
    if (token) {
      const retryResponse = await performRequest(path, init);
      return parseEnvelope<T>(retryResponse);
    }
  }

  return parseEnvelope<T>(response);
}

export { ApiError } from "./types";
