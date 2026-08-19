"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { apiRequest, configureApiClient } from "@/lib/api/client";
import type { CurrentUser, LoginResult, RegisteredAccount } from "@/lib/api/types";

/**
 * Authentification (../../backend/CLAUDE.md section 8, docs/06-technical-architecture.md
 * ADR-007) : l'access token JWT ne vit qu'en mémoire ici (jamais localStorage/sessionStorage,
 * jamais persisté). Le refresh token est un cookie HttpOnly invisible en JavaScript — cette
 * classe ne le lit, ni ne le manipule jamais, elle ne fait qu'appeler /auth/refresh, que le
 * navigateur porte automatiquement.
 *
 * "restoring" existe pour ne jamais afficher un état "déconnecté" pendant le court instant
 * où le refresh silencieux au montage n'a pas encore répondu (sinon un F5 renverrait
 * brièvement l'utilisateur vers /login avant de le raccrocher à sa session).
 */
type AuthStatus = "restoring" | "authenticated" | "anonymous";

interface AuthContextValue {
  status: AuthStatus;
  user: CurrentUser | null;
  login: (email: string, password: string) => Promise<void>;
  register: (email: string, password: string) => Promise<RegisteredAccount>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const accessTokenRef = useRef<string | null>(null);
  const refreshInFlightRef = useRef<Promise<string | null> | null>(null);
  const [status, setStatus] = useState<AuthStatus>("restoring");
  const [user, setUser] = useState<CurrentUser | null>(null);

  const refreshAccessToken = useCallback((): Promise<string | null> => {
    // Single-flight (plan Phase 2) : gesdinet's refresh token est à usage unique
    // (single_use: true) — si deux requêtes en 401 déclenchaient chacune leur propre
    // refresh, la seconde consommerait un jeton déjà invalidé par la première.
    if (refreshInFlightRef.current) {
      return refreshInFlightRef.current;
    }

    const attempt = (async () => {
      try {
        const response = await fetch("/api/v1/auth/refresh", {
          method: "POST",
          credentials: "include",
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
    configureApiClient({
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
        setStatus("anonymous");
        return;
      }
      try {
        const me = await apiRequest<CurrentUser>("/api/v1/users/current");
        if (!cancelled) {
          setUser(me);
          setStatus("authenticated");
        }
      } catch {
        accessTokenRef.current = null;
        if (!cancelled) {
          setStatus("anonymous");
        }
      }
    })();

    return () => {
      cancelled = true;
    };
    // Restauration de session au montage uniquement : refreshAccessToken est stable
    // (useCallback sans dépendance), pas besoin de le relancer à chaque rendu.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const login = useCallback(async (email: string, password: string) => {
    const result = await apiRequest<LoginResult>("/api/v1/auth/login", {
      method: "POST",
      auth: false,
      body: { email, password },
    });
    accessTokenRef.current = result.token;
    const me = await apiRequest<CurrentUser>("/api/v1/users/current");
    setUser(me);
    setStatus("authenticated");
  }, []);

  const register = useCallback(async (email: string, password: string) => {
    // US-AUTH-001 : l'inscription ne connecte jamais automatiquement le compte créé
    // (docs/08-api-specification.md, section 23) — register et login restent deux appels
    // distincts, cohérent avec le contrôleur backend.
    return apiRequest<RegisteredAccount>("/api/v1/auth/register", {
      method: "POST",
      auth: false,
      body: { email, password },
    });
  }, []);

  const logout = useCallback(async () => {
    try {
      await apiRequest("/api/v1/auth/logout", { method: "POST" });
    } catch {
      // Le nettoyage de session local ci-dessous doit avoir lieu même si l'appel réseau
      // échoue (backend indisponible, connexion coupée) : jamais un utilisateur restant
      // authentifié côté client parce que la requête de logout a échoué.
    } finally {
      accessTokenRef.current = null;
      setUser(null);
      setStatus("anonymous");
    }
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({ status, user, login, register, logout }),
    [status, user, login, register, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth doit être utilisé à l'intérieur d'un AuthProvider.");
  }
  return context;
}
