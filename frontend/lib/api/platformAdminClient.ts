import { ApiError, isApiErrorBody, type PaginationMeta } from './types';

/**
 * Client API dédié à la surface Platform Administration (plan Phase 15, ADR-009) - jamais le
 * singleton de lib/api/client.ts, qui appartient exclusivement à la session tenant. Une
 * session tenant et une session PlatformAdministrator doivent pouvoir coexister dans le même
 * onglet sans se marcher dessus (chacune son propre access token en mémoire, configuré par
 * son propre AuthProvider - components/platform-admin/PlatformAdminAuthProvider.tsx).
 * Réutilise ApiError/isApiErrorBody (lib/api/types.ts) : le contrat d'erreur est identique
 * sur les deux espaces d'API.
 */
interface PlatformAdminApiClientConfig {
  getAccessToken: () => string | null;
  refreshAccessToken: () => Promise<string | null>;
}

let config: PlatformAdminApiClientConfig = {
  getAccessToken: () => null,
  refreshAccessToken: async () => null,
};

export function configurePlatformAdminApiClient(next: PlatformAdminApiClientConfig): void {
  config = next;
}

interface ApiRequestInit extends Omit<RequestInit, 'body'> {
  body?: unknown;
  /** Ajoute l'en-tête Authorization et tente un refresh sur 401. Défaut : true. */
  auth?: boolean;
}

async function performRequest(path: string, init: ApiRequestInit): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set('Content-Type', 'application/json');

  if (init.auth !== false) {
    const token = config.getAccessToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }
  }

  return fetch(path, {
    ...init,
    headers,
    // Cookie platform_admin_refresh_token, HttpOnly, distinct du cookie refresh_token
    // tenant (backend/config/packages/security.yaml) - transmis en same-origin.
    credentials: 'include',
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
      error: {
        code: 'UNKNOWN_ERROR',
        message: 'Une erreur inattendue est survenue.',
        details: [],
        request_id: null,
      },
    });
  }

  if (null === body) {
    return undefined as T;
  }

  return (body as { data: T }).data;
}

export async function platformAdminApiRequest<T>(
  path: string,
  init: ApiRequestInit = {},
): Promise<T> {
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

async function parsePaginatedEnvelope<T>(
  response: Response,
): Promise<{ data: T[]; meta: { pagination: PaginationMeta } }> {
  const raw = await response.text();
  const body: unknown = raw.length > 0 ? JSON.parse(raw) : null;

  if (!response.ok) {
    if (isApiErrorBody(body)) {
      throw new ApiError(response.status, body);
    }
    throw new ApiError(response.status, {
      error: {
        code: 'UNKNOWN_ERROR',
        message: 'Une erreur inattendue est survenue.',
        details: [],
        request_id: null,
      },
    });
  }

  return body as { data: T[]; meta: { pagination: PaginationMeta } };
}

export async function platformAdminApiRequestPaginated<T>(
  path: string,
  init: ApiRequestInit = {},
): Promise<{ data: T[]; meta: { pagination: PaginationMeta } }> {
  const response = await performRequest(path, init);

  if (response.status === 401 && init.auth !== false) {
    const token = await config.refreshAccessToken();
    if (token) {
      const retryResponse = await performRequest(path, init);
      return parsePaginatedEnvelope<T>(retryResponse);
    }
  }

  return parsePaginatedEnvelope<T>(response);
}

export { ApiError } from './types';
