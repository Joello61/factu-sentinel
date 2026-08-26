'use client';

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';
import {
  platformAdminApiRequest,
  configurePlatformAdminApiClient,
} from '@/lib/api/platformAdminClient';
import type { PlatformAdministratorSession } from '@/lib/api/platformAdminTypes';

/**
 * Contexte d'authentification dédié à la surface Platform Administration (plan Phase 15,
 * ADR-009) - jamais components/auth/AuthProvider.tsx, réservé à la session tenant. Même
 * principe de sécurité (access token en mémoire uniquement, jamais localStorage ; refresh
 * token en cookie HttpOnly invisible en JavaScript - ici platform_admin_refresh_token, nom
 * distinct du cookie tenant refresh_token pour ne jamais collisionner dans le même
 * navigateur).
 *
 * "mfa_required" est un état de session à part entière, jamais un simple booléen de formulaire
 * - le ticket de challenge est gardé en mémoire (jamais exposé au composant appelant) tant que
 * le code TOTP n'a pas été vérifié (plan Phase 15 : "l'état intermédiaire mfa_required n'est
 * jamais lui-même un moyen de contournement").
 */
type PlatformAdminAuthStatus = 'restoring' | 'authenticated' | 'anonymous' | 'mfa_required';

interface PlatformAdminAuthContextValue {
  status: PlatformAdminAuthStatus;
  administrator: PlatformAdministratorSession | null;
  login: (email: string, password: string) => Promise<void>;
  verifyMfa: (code: string) => Promise<void>;
  cancelMfa: () => void;
  logout: () => Promise<void>;
}

const PlatformAdminAuthContext = createContext<PlatformAdminAuthContextValue | null>(null);

export function PlatformAdminAuthProvider({ children }: { children: ReactNode }) {
  const accessTokenRef = useRef<string | null>(null);
  const challengeRef = useRef<string | null>(null);
  const refreshInFlightRef = useRef<Promise<string | null> | null>(null);
  const [status, setStatus] = useState<PlatformAdminAuthStatus>('restoring');
  const [administrator, setAdministrator] = useState<PlatformAdministratorSession | null>(null);

  const refreshAccessToken = useCallback((): Promise<string | null> => {
    // Single-flight (même patron que components/auth/AuthProvider.tsx) : le refresh token
    // est à usage unique (single_use, backend/config/packages/security.yaml).
    if (refreshInFlightRef.current) {
      return refreshInFlightRef.current;
    }

    const attempt = (async () => {
      try {
        const response = await fetch('/api/v1/platform-admin/auth/refresh', {
          method: 'POST',
          credentials: 'include',
        });
        if (!response.ok) {
          accessTokenRef.current = null;
          return null;
        }
        const body: { data?: { token?: string } } = await response.json();
        accessTokenRef.current = body.data?.token ?? null;
        return accessTokenRef.current;
      } catch {
        accessTokenRef.current = null;
        return null;
      } finally {
        refreshInFlightRef.current = null;
      }
    })();

    refreshInFlightRef.current = attempt;
    return attempt;
  }, []);

  useEffect(() => {
    configurePlatformAdminApiClient({
      getAccessToken: () => accessTokenRef.current,
      refreshAccessToken,
    });
  }, [refreshAccessToken]);

  useEffect(() => {
    let cancelled = false;

    (async () => {
      const token = await refreshAccessToken();
      if (cancelled) {
        return;
      }
      if (!token) {
        setStatus('anonymous');
        return;
      }
      try {
        const me = await platformAdminApiRequest<PlatformAdministratorSession>('/api/v1/platform-admin/me');
        if (!cancelled) {
          setAdministrator(me);
          setStatus('authenticated');
        }
      } catch {
        accessTokenRef.current = null;
        if (!cancelled) {
          setStatus('anonymous');
        }
      }
    })();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const result = await platformAdminApiRequest<{ status: string; mfa_challenge: string }>(
      '/api/v1/platform-admin/auth/login',
      { method: 'POST', auth: false, body: { email, password } },
    );
    challengeRef.current = result.mfa_challenge;
    setStatus('mfa_required');
  }, []);

  const verifyMfa = useCallback(async (code: string) => {
    const challenge = challengeRef.current;
    if (!challenge) {
      throw new Error('Aucune tentative de connexion en cours.');
    }
    const result = await platformAdminApiRequest<{ token: string }>(
      '/api/v1/platform-admin/auth/mfa/verify',
      { method: 'POST', auth: false, body: { mfa_challenge: challenge, code } },
    );
    challengeRef.current = null;
    accessTokenRef.current = result.token;
    const me = await platformAdminApiRequest<PlatformAdministratorSession>('/api/v1/platform-admin/me');
    setAdministrator(me);
    setStatus('authenticated');
  }, []);

  const cancelMfa = useCallback(() => {
    challengeRef.current = null;
    setStatus('anonymous');
  }, []);

  const logout = useCallback(async () => {
    try {
      await platformAdminApiRequest('/api/v1/platform-admin/auth/logout', { method: 'POST' });
    } catch {
      // Le nettoyage de session local ci-dessous doit avoir lieu même si l'appel réseau
      // échoue (même discipline que components/auth/AuthProvider.tsx).
    } finally {
      accessTokenRef.current = null;
      challengeRef.current = null;
      setAdministrator(null);
      setStatus('anonymous');
    }
  }, []);

  const value = useMemo<PlatformAdminAuthContextValue>(
    () => ({ status, administrator, login, verifyMfa, cancelMfa, logout }),
    [status, administrator, login, verifyMfa, cancelMfa, logout],
  );

  return (
    <PlatformAdminAuthContext.Provider value={value}>{children}</PlatformAdminAuthContext.Provider>
  );
}

export function usePlatformAdminAuth(): PlatformAdminAuthContextValue {
  const context = useContext(PlatformAdminAuthContext);
  if (!context) {
    throw new Error('usePlatformAdminAuth doit être utilisé à l\'intérieur d\'un PlatformAdminAuthProvider.');
  }
  return context;
}
