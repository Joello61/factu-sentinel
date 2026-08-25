import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { PlatformAdminAuthProvider, usePlatformAdminAuth } from "./PlatformAdminAuthProvider";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
    json: async () => body,
  } as Response;
}

const ADMINISTRATOR = { email: "admin@example.test", created_at: "2026-08-21T00:00:00+00:00" };

/** Harnais exposant l'état/actions de PlatformAdminAuthProvider - même patron que components/auth/AuthProvider.test.tsx. */
function Harness() {
  const auth = usePlatformAdminAuth();
  return (
    <div>
      <span data-testid="status">{auth.status}</span>
      <span data-testid="email">{auth.administrator?.email ?? ""}</span>
      <button onClick={() => void auth.login("admin@example.test", "a-very-long-password-1234")}>login</button>
      <button onClick={() => void auth.verifyMfa("123456")}>verify</button>
    </div>
  );
}

function urlOf(input: RequestInfo | URL): string {
  return typeof input === "string" ? input : input.toString();
}

describe("PlatformAdminAuthProvider", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("moves through mfa_required before becoming authenticated - never a shortcut", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockImplementation(async (input) => {
      const url = urlOf(input);
      if (url === "/api/v1/platform-admin/auth/refresh") {
        return jsonResponse(401, {
          error: { code: "AUTHENTICATION_FAILED", message: "x", details: [], request_id: null },
        });
      }
      if (url === "/api/v1/platform-admin/auth/login") {
        return jsonResponse(200, { data: { status: "mfa_required", mfa_challenge: "chal-1" } });
      }
      if (url === "/api/v1/platform-admin/auth/mfa/verify") {
        return jsonResponse(200, { data: { token: "tok-1" } });
      }
      if (url === "/api/v1/platform-admin/me") {
        return jsonResponse(200, { data: ADMINISTRATOR });
      }
      throw new Error(`Unexpected fetch to ${url}`);
    });

    render(
      <PlatformAdminAuthProvider>
        <Harness />
      </PlatformAdminAuthProvider>,
    );
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("anonymous"));

    await user.click(screen.getByRole("button", { name: "login" }));
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("mfa_required"));
    // Aucune identité exposée avant la vérification MFA.
    expect(screen.getByTestId("email")).toHaveTextContent("");

    await user.click(screen.getByRole("button", { name: "verify" }));
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("authenticated"));
    expect(screen.getByTestId("email")).toHaveTextContent(ADMINISTRATOR.email);
  });

  it("restores an authenticated session on mount via a silent refresh", async () => {
    vi.mocked(fetch).mockImplementation(async (input) => {
      const url = urlOf(input);
      if (url === "/api/v1/platform-admin/auth/refresh") {
        return jsonResponse(200, { data: { token: "tok-1" } });
      }
      if (url === "/api/v1/platform-admin/me") {
        return jsonResponse(200, { data: ADMINISTRATOR });
      }
      throw new Error(`Unexpected fetch to ${url}`);
    });

    render(
      <PlatformAdminAuthProvider>
        <Harness />
      </PlatformAdminAuthProvider>,
    );

    expect(screen.getByTestId("status")).toHaveTextContent("restoring");
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("authenticated"));
    expect(screen.getByTestId("email")).toHaveTextContent(ADMINISTRATOR.email);
  });
});
