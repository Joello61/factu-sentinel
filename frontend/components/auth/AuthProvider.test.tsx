import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { AuthProvider, useAuth } from "./AuthProvider";
import { apiRequest } from "@/lib/api/client";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    // AuthProvider.refreshAccessToken() appelle response.json() directement (hors du
    // client API, qui utilise .text() — voir lib/api/client.test.ts) : les deux doivent
    // être fournis ici.
    text: async () => JSON.stringify(body),
    json: async () => body,
  } as Response;
}

const CURRENT_USER = {
  id: "user-1",
  email: "browser-e2e@example.test",
  email_verified_at: null,
  created_at: "2026-08-19T00:00:00+00:00",
};

/** Petit harnais exposant l'état/actions d'AuthProvider pour les assertions RTL. */
function Harness() {
  const auth = useAuth();
  return (
    <div>
      <span data-testid="status">{auth.status}</span>
      <span data-testid="email">{auth.user?.email ?? ""}</span>
      <button onClick={() => void auth.login("browser-e2e@example.test", "a-very-long-password-1234")}>
        login
      </button>
      <button onClick={() => void auth.logout()}>logout</button>
    </div>
  );
}

function urlOf(input: RequestInfo | URL): string {
  return typeof input === "string" ? input : input.toString();
}

describe("AuthProvider", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("restores an authenticated session on mount via a silent refresh", async () => {
    vi.mocked(fetch).mockImplementation(async (input) => {
      const url = urlOf(input);
      if (url === "/api/v1/auth/refresh") return jsonResponse(200, { data: { token: "tok-1" } });
      if (url === "/api/v1/users/current") return jsonResponse(200, { data: CURRENT_USER });
      throw new Error(`Unexpected fetch to ${url}`);
    });

    render(
      <AuthProvider>
        <Harness />
      </AuthProvider>,
    );

    expect(screen.getByTestId("status")).toHaveTextContent("restoring");
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("authenticated"));
    expect(screen.getByTestId("email")).toHaveTextContent(CURRENT_USER.email);
  });

  it("becomes anonymous when there is no valid refresh cookie", async () => {
    vi.mocked(fetch).mockImplementation(async (input) => {
      const url = urlOf(input);
      if (url === "/api/v1/auth/refresh") return jsonResponse(401, { error: { code: "AUTHENTICATION_FAILED", message: "x", details: [], request_id: null } });
      throw new Error(`Unexpected fetch to ${url}`);
    });

    render(
      <AuthProvider>
        <Harness />
      </AuthProvider>,
    );

    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("anonymous"));
    expect(screen.getByTestId("email")).toHaveTextContent("");
  });

  it("login() authenticates and exposes the current user", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockImplementation(async (input) => {
      const url = urlOf(input);
      if (url === "/api/v1/auth/refresh") return jsonResponse(401, { error: { code: "AUTHENTICATION_FAILED", message: "x", details: [], request_id: null } });
      if (url === "/api/v1/auth/login") return jsonResponse(200, { data: { token: "tok-2" } });
      if (url === "/api/v1/users/current") return jsonResponse(200, { data: CURRENT_USER });
      throw new Error(`Unexpected fetch to ${url}`);
    });

    render(
      <AuthProvider>
        <Harness />
      </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("anonymous"));

    await user.click(screen.getByRole("button", { name: "login" }));

    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("authenticated"));
    expect(screen.getByTestId("email")).toHaveTextContent(CURRENT_USER.email);
  });

  it("logout() clears the session even if the request fails", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockImplementation(async (input) => {
      const url = urlOf(input);
      if (url === "/api/v1/auth/refresh") return jsonResponse(200, { data: { token: "tok-1" } });
      if (url === "/api/v1/users/current") return jsonResponse(200, { data: CURRENT_USER });
      if (url === "/api/v1/auth/logout") return jsonResponse(500, { error: { code: "INTERNAL_ERROR", message: "x", details: [], request_id: null } });
      throw new Error(`Unexpected fetch to ${url}`);
    });

    render(
      <AuthProvider>
        <Harness />
      </AuthProvider>,
    );
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("authenticated"));

    await user.click(screen.getByRole("button", { name: "logout" }));

    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("anonymous"));
    expect(screen.getByTestId("email")).toHaveTextContent("");
  });

  it("dedupes concurrent refreshes into a single request (single-flight)", async () => {
    let refreshed = false;
    const refreshCalls: string[] = [];
    vi.mocked(fetch).mockImplementation(async (input) => {
      const url = urlOf(input);
      if (url === "/api/v1/auth/refresh") {
        refreshCalls.push(url);
        refreshed = true;
        return jsonResponse(200, { data: { token: "tok-1" } });
      }
      if (url === "/api/v1/users/current") return jsonResponse(200, { data: CURRENT_USER });
      if (url === "/api/v1/protected") {
        return refreshed
          ? jsonResponse(200, { data: { ok: true } })
          : jsonResponse(401, { error: { code: "AUTHENTICATION_FAILED", message: "x", details: [], request_id: null } });
      }
      throw new Error(`Unexpected fetch to ${url}`);
    });

    render(
      <AuthProvider>
        <Harness />
      </AuthProvider>,
    );
    // Session initiale déjà restaurée pour ne tester que le comportement en 401 applicatif
    // (ex. access token expiré côté serveur avant le refresh token), pas l'amorçage.
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("authenticated"));
    refreshCalls.length = 0;
    refreshed = false;

    const [a, b] = await Promise.all([
      apiRequest<{ ok: boolean }>("/api/v1/protected"),
      apiRequest<{ ok: boolean }>("/api/v1/protected"),
    ]);

    expect(a).toEqual({ ok: true });
    expect(b).toEqual({ ok: true });
    expect(refreshCalls).toHaveLength(1);
  });
});
