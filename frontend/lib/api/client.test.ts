import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { apiRequest, configureApiClient } from "./client";
import { ApiError } from "./types";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as Response;
}

describe("apiRequest", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
    configureApiClient({
      getAccessToken: () => null,
      refreshAccessToken: async () => null,
    });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("unwraps the data envelope on success", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(jsonResponse(200, { data: { id: "1" } }));

    const result = await apiRequest<{ id: string }>("/api/v1/whatever");

    expect(result).toEqual({ id: "1" });
  });

  it("throws a typed ApiError built from the error envelope on failure", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(
      jsonResponse(422, {
        error: {
          code: "VALIDATION_ERROR",
          message: "La requête contient des champs invalides.",
          details: [{ field: "email", issue: "Cette valeur n'est pas une adresse email valide." }],
          request_id: "abc-123",
        },
      }),
    );

    await expect(apiRequest("/api/v1/auth/register", { method: "POST", body: {} })).rejects.toMatchObject({
      status: 422,
      code: "VALIDATION_ERROR",
      details: [{ field: "email", issue: "Cette valeur n'est pas une adresse email valide." }],
      requestId: "abc-123",
    });
  });

  it("falls back to a generic ApiError when the error body cannot be parsed", async () => {
    vi.mocked(fetch).mockResolvedValueOnce({ ok: false, status: 500, text: async () => "" } as Response);

    await expect(apiRequest("/api/v1/whatever")).rejects.toMatchObject({ status: 500, code: "UNKNOWN_ERROR" });
  });

  it("retries exactly once via refreshAccessToken on a 401, then succeeds", async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce(
        jsonResponse(401, { error: { code: "AUTHENTICATION_FAILED", message: "x", details: [], request_id: null } }),
      )
      .mockResolvedValueOnce(jsonResponse(200, { data: { ok: true } }));

    const refreshAccessToken = vi.fn().mockResolvedValue("new-token");
    configureApiClient({ getAccessToken: () => null, refreshAccessToken });

    const result = await apiRequest<{ ok: boolean }>("/api/v1/protected");

    expect(refreshAccessToken).toHaveBeenCalledTimes(1);
    expect(fetch).toHaveBeenCalledTimes(2);
    expect(result).toEqual({ ok: true });
  });

  it("does not retry when refreshAccessToken cannot restore a session", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(
      jsonResponse(401, { error: { code: "AUTHENTICATION_FAILED", message: "x", details: [], request_id: null } }),
    );

    configureApiClient({ getAccessToken: () => null, refreshAccessToken: vi.fn().mockResolvedValue(null) });

    await expect(apiRequest("/api/v1/protected")).rejects.toMatchObject({ status: 401 });
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it("never attempts a refresh when auth: false is passed (public endpoints)", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(
      jsonResponse(401, { error: { code: "AUTHENTICATION_FAILED", message: "x", details: [], request_id: null } }),
    );
    const refreshAccessToken = vi.fn();
    configureApiClient({ getAccessToken: () => null, refreshAccessToken });

    await expect(apiRequest("/api/v1/auth/login", { auth: false })).rejects.toBeInstanceOf(ApiError);
    expect(refreshAccessToken).not.toHaveBeenCalled();
  });

  it("sends the access token as a Bearer header when auth is enabled", async () => {
    vi.mocked(fetch).mockResolvedValueOnce(jsonResponse(200, { data: {} }));
    configureApiClient({ getAccessToken: () => "the-token", refreshAccessToken: async () => null });

    await apiRequest("/api/v1/whatever");

    const [, init] = vi.mocked(fetch).mock.calls[0];
    const headers = new Headers(init?.headers);
    expect(headers.get("Authorization")).toBe("Bearer the-token");
  });
});
