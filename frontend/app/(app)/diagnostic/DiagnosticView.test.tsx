import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { DiagnosticView } from "./DiagnosticView";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as Response;
}

describe("DiagnosticView", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("shows the not-configured state on a 404, with a link to /company", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(404, { error: { code: "HTTP_ERROR", message: "Not found", details: [], request_id: null } }),
    );

    render(<DiagnosticView />);

    await waitFor(() => expect(screen.getByText(/configurez d'abord votre entreprise/i)).toBeInTheDocument());
    expect(screen.getByRole("link", { name: /configurer mon entreprise/i })).toHaveAttribute("href", "/company");
  });

  it("renders reception and emission dates with the explanation once available", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, {
        data: {
          reception_obligation_date: "2026-09-01",
          emission_obligation_date: "2027-09-01",
          computed_at: "2026-08-19T12:00:00+00:00",
          explanation: "Vous bénéficiez de la franchise en base de TVA.",
        },
      }),
    );

    render(<DiagnosticView />);

    await waitFor(() => expect(screen.getByText(/franchise en base de TVA/)).toBeInTheDocument());
    expect(screen.getByText(/1 septembre 2026/)).toBeInTheDocument();
    expect(screen.getByText(/1 septembre 2027/)).toBeInTheDocument();
  });

  it("shows an out-of-scope message when both dates are null", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, {
        data: {
          reception_obligation_date: null,
          emission_obligation_date: null,
          computed_at: "2026-08-19T12:00:00+00:00",
          explanation: "Votre entreprise n'est pas assujettie à la TVA.",
        },
      }),
    );

    render(<DiagnosticView />);

    await waitFor(() => expect(screen.getByText(/n'est, en l'état des informations renseignées, pas concernée/)).toBeInTheDocument());
  });
});
