import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor, fireEvent, within } from "@testing-library/react";
import { ComplianceAnalysisHistory } from "./ComplianceAnalysisHistory";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => null },
    text: async () => JSON.stringify(body),
  } as unknown as Response;
}

function historyItem(id: string, invoiceNumber: string, globalResult: string) {
  return {
    id,
    invoice_id: `invoice-${id}`,
    invoice_number: invoiceNumber,
    status: "COMPLETED",
    global_result: globalResult,
    triggered_at: "2026-08-15T10:00:00Z",
    completed_at: "2026-08-15T10:00:01Z",
  };
}

describe("ComplianceAnalysisHistory", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("shows an empty state when no analysis has ever been run", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, { data: [], meta: { pagination: { page: 1, per_page: 20, total_count: 0, total_pages: 0 } } }),
    );

    render(<ComplianceAnalysisHistory />);

    await waitFor(() => expect(screen.getByText(/aucune analyse de conformité effectuée/i)).toBeInTheDocument());
  });

  it("renders the invoice number, date, result and a link to the detail page for each row", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, {
        data: [historyItem("analysis-1", "F-2026-001", "NON_CONFORME")],
        meta: { pagination: { page: 1, per_page: 20, total_count: 1, total_pages: 1 } },
      }),
    );

    render(<ComplianceAnalysisHistory />);

    // Deux rendus responsive coexistent dans le DOM (docs/11-frontend-design-system.md,
    // section 24 : tableau desktop masqué en CSS, liste de cartes mobile masquée en CSS -
    // jsdom ne charge pas Tailwind, donc les deux sont "visibles" pour testing-library).
    // Le tableau reste la source canonique pour ces assertions ; la carte mobile est
    // vérifiée séparément ci-dessous.
    const table = await screen.findByRole("table");
    expect(within(table).getByText("F-2026-001")).toBeInTheDocument();
    expect(within(table).getByText("Non conforme")).toBeInTheDocument();
    expect(within(table).getByRole("link", { name: /consulter/i })).toHaveAttribute(
      "href",
      "/history/analysis-1",
    );

    const mobileList = screen.getByRole("list");
    expect(within(mobileList).getByText("F-2026-001")).toBeInTheDocument();
    expect(within(mobileList).getByText("Non conforme")).toBeInTheDocument();
    expect(within(mobileList).getByRole("link")).toHaveAttribute("href", "/history/analysis-1");
  });

  it("requests the next page when clicking Suivant", async () => {
    vi.mocked(fetch).mockImplementation(async (input: RequestInfo | URL) => {
      // "per_page=20" contient lui-même la sous-chaîne "page=2" -- new URL(...).searchParams
      // évite ce faux-positif, contrairement à un simple String.includes("page=2"). client.ts
      // appelle fetch() avec un chemin relatif (pas d'origine) : base explicite requise ici.
      const page = Number(new URL(String(input), "http://localhost").searchParams.get("page"));
      return jsonResponse(200, {
        data: [historyItem(`analysis-${page}`, `F-2026-00${page}`, "CONFORME")],
        meta: { pagination: { page, per_page: 20, total_count: 25, total_pages: 2 } },
      });
    });

    render(<ComplianceAnalysisHistory />);

    const table = await screen.findByRole("table");
    await waitFor(() => expect(within(table).getByText("F-2026-001")).toBeInTheDocument());
    fireEvent.click(screen.getByRole("button", { name: /suivant/i }));

    await waitFor(() => expect(within(table).getByText("F-2026-002")).toBeInTheDocument());
    expect(vi.mocked(fetch).mock.calls.some((call) => String(call[0]).includes("page=2"))).toBe(true);
  });

  it("shows an error message when the history cannot be loaded", async () => {
    vi.mocked(fetch).mockResolvedValue(jsonResponse(500, { error: { code: "INTERNAL", message: "boom", details: [], request_id: null } }));

    render(<ComplianceAnalysisHistory />);

    await waitFor(() => expect(screen.getByRole("alert")).toBeInTheDocument());
  });
});
