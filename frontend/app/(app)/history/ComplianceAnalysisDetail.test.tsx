import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { ComplianceAnalysisDetail } from "./ComplianceAnalysisDetail";

const ANALYSIS_ID = "analysis-1";
const INVOICE_ID = "invoice-1";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: () => null },
    text: async () => JSON.stringify(body),
  } as unknown as Response;
}

function routeFetch(routes: { match: (method: string, url: string) => boolean; response: () => Response }[]) {
  return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input);
    const method = (init?.method ?? "GET").toUpperCase();
    const route = routes.find((candidate) => candidate.match(method, url));
    if (!route) {
      throw new Error(`Requête non mockée : ${method} ${url}`);
    }
    return route.response();
  });
}

const ANALYSIS = {
  id: ANALYSIS_ID,
  invoice_id: INVOICE_ID,
  status: "COMPLETED",
  global_result: "NON_CONFORME",
  triggered_at: "2026-08-15T10:00:00Z",
  completed_at: "2026-08-15T10:00:01Z",
  findings: [
    {
      id: "finding-1",
      result: "NON_CONFORME",
      message: "Le numéro SIREN de votre client professionnel français est absent de cette facture.",
      related_field: "customer.siren",
      observed_value: null,
      correction_action: "Renseignez le numéro SIREN de votre client, puis relancez l'analyse.",
      rule: {
        id: "mention-siren-client",
        version: 1,
        source_reference: "docs/02-regulatory-study.md, section 10",
        confidence_level: "ELEVE",
        effective_from: "2026-01-01",
        effective_until: null,
      },
    },
  ],
};

const INVOICE = {
  id: INVOICE_ID,
  customer_id: "customer-1",
  invoice_number: "F-2026-001",
  issue_date: "2026-08-15",
  operation_type: "PRESTATION_SERVICE",
  currency: "EUR",
  total_amount_ht: "100.00",
  total_amount_ttc: "120.00",
  vat_exemption_reason: null,
  status: "ANALYZED",
  source: "SAISIE_MANUELLE",
  documents: [],
  lines: [],
  created_at: "2026-08-15T00:00:00Z",
  updated_at: "2026-08-15T00:00:00Z",
};

describe("ComplianceAnalysisDetail", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("renders the invoice number, the historical result and a link back to the current invoice", async () => {
    vi.mocked(fetch).mockImplementation(
      routeFetch([
        { match: (m, u) => "GET" === m && u.endsWith(`/compliance-analyses/${ANALYSIS_ID}`), response: () => jsonResponse(200, { data: ANALYSIS }) },
        { match: (m, u) => "GET" === m && u.endsWith(`/invoices/${INVOICE_ID}`), response: () => jsonResponse(200, { data: INVOICE }) },
      ]),
    );

    render(<ComplianceAnalysisDetail analysisId={ANALYSIS_ID} />);

    await waitFor(() => expect(screen.getByText("F-2026-001")).toBeInTheDocument());
    // "Non conforme" apparaît deux fois (badge global de ComplianceResultSummary + badge du
    // finding dans ComplianceFindingCard) -- même pattern que InvoiceDetail.test.tsx.
    expect(screen.getAllByText("Non conforme").length).toBeGreaterThan(0);
    expect(
      screen.getByText("Le numéro SIREN de votre client professionnel français est absent de cette facture."),
    ).toBeInTheDocument();
    // Lecture seule : jamais de bouton de relance d'analyse sur une page d'historique.
    expect(screen.queryByRole("button", { name: /relancer l'analyse/i })).not.toBeInTheDocument();
    expect(screen.getByRole("link", { name: /voir la facture actuelle/i })).toHaveAttribute("href", `/invoices/${INVOICE_ID}`);
  });

  it("shows a not-found message for an analysis that does not exist or belongs to another organization", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(404, { error: { code: "NOT_FOUND", message: "not found", details: [], request_id: null } }),
    );

    render(<ComplianceAnalysisDetail analysisId={ANALYSIS_ID} />);

    await waitFor(() => expect(screen.getByText(/n'existe pas ou n'est plus disponible/i)).toBeInTheDocument());
  });
});
