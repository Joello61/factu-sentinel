import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import { InvoiceDetail } from "./InvoiceDetail";

const INVOICE_ID = "invoice-1";
const CUSTOMER_ID = "customer-1";

function jsonResponse(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: (name: string) => headers[name] ?? null },
    text: async () => JSON.stringify(body),
  } as unknown as Response;
}

function invoiceFixture(status: string) {
  return {
    id: INVOICE_ID,
    customer_id: CUSTOMER_ID,
    invoice_number: "F-2026-001",
    issue_date: "2026-08-15",
    operation_type: "PRESTATION_SERVICE",
    currency: "EUR",
    total_amount_ht: "100.00",
    total_amount_ttc: "120.00",
    vat_exemption_reason: null,
    status,
    source: "SAISIE_MANUELLE",
    documents: [],
    lines: [
      {
        id: "line-1",
        description: "Prestation",
        quantity: "1",
        unit_price_ht: "100.00",
        vat_rate: "0.20",
        line_amount_ht: "100.00",
        line_amount_vat: "20.00",
        line_amount_ttc: "120.00",
      },
    ],
    created_at: "2026-08-15T00:00:00Z",
    updated_at: "2026-08-15T00:00:00Z",
  };
}

const CUSTOMER = {
  id: CUSTOMER_ID,
  customer_type: "PROFESSIONNEL_FRANCAIS",
  name: "Client Test SARL",
  siren: null,
  vat_number: null,
  country: "FR",
  created_at: "2026-08-01T00:00:00Z",
  updated_at: "2026-08-01T00:00:00Z",
};

function analysisSummary(id: string, globalResult: string, completedAt: string) {
  return { id, status: "COMPLETED", global_result: globalResult, triggered_at: completedAt, completed_at: completedAt };
}

function conformeAnalysis(id: string, completedAt: string) {
  return {
    id,
    invoice_id: INVOICE_ID,
    status: "COMPLETED",
    global_result: "CONFORME",
    triggered_at: completedAt,
    completed_at: completedAt,
    findings: [],
  };
}

function nonConformeAnalysis(id: string, completedAt: string) {
  return {
    id,
    invoice_id: INVOICE_ID,
    status: "COMPLETED",
    global_result: "NON_CONFORME",
    triggered_at: completedAt,
    completed_at: completedAt,
    findings: [
      {
        id: "finding-1",
        result: "NON_CONFORME",
        message: "Le numéro SIREN de votre client professionnel français est absent de cette facture.",
        related_field: "customer.siren",
        observed_value: null,
        correction_action: "Renseignez le numéro SIREN de votre client dans sa fiche client, puis relancez l'analyse.",
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
}

/**
 * Route les requêtes fetch par méthode + fragment d'URL, plutôt que par ordre d'appel
 * (l'ordre réel dépend de l'état, ex. le fetch de l'analyse n'a lieu que si le statut le
 * justifie -- voir InvoiceDetail).
 */
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

describe("InvoiceDetail", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("shows a CTA to trigger the first analysis when the invoice has none yet", async () => {
    vi.mocked(fetch).mockImplementation(
      routeFetch([
        { match: (m, u) => "GET" === m && u.endsWith(`/invoices/${INVOICE_ID}`), response: () => jsonResponse(200, { data: invoiceFixture("READY_FOR_ANALYSIS") }) },
        { match: (m, u) => "GET" === m && u.endsWith(`/customers/${CUSTOMER_ID}`), response: () => jsonResponse(200, { data: CUSTOMER }) },
      ]),
    );

    render(<InvoiceDetail invoiceId={INVOICE_ID} />);

    await waitFor(() => expect(screen.getByText(/n'a pas encore été analysée/)).toBeInTheDocument());
    expect(screen.getByRole("button", { name: /^lancer l'analyse$/i })).toBeInTheDocument();
    // Une facture jamais analysée n'a par construction aucune ComplianceAnalysis : le
    // fetch de l'analyse ne doit jamais être déclenché ici (invariant Invoice, voir plan).
    expect(vi.mocked(fetch).mock.calls.some((call) => String(call[0]).includes("compliance-analyses"))).toBe(false);
  });

  it("renders a CONFORME result for an already-analyzed invoice", async () => {
    vi.mocked(fetch).mockImplementation(
      routeFetch([
        { match: (m, u) => "GET" === m && u.endsWith(`/invoices/${INVOICE_ID}`), response: () => jsonResponse(200, { data: invoiceFixture("ANALYZED") }) },
        { match: (m, u) => "GET" === m && u.endsWith(`/customers/${CUSTOMER_ID}`), response: () => jsonResponse(200, { data: CUSTOMER }) },
        {
          match: (m, u) => "GET" === m && u.includes("compliance-analyses?per_page=1"),
          response: () =>
            jsonResponse(200, {
              data: [analysisSummary("analysis-1", "CONFORME", "2026-08-15T10:00:00Z")],
              meta: { pagination: { page: 1, per_page: 1, total_count: 1, total_pages: 1 } },
            }),
        },
        { match: (m, u) => "GET" === m && u.endsWith("/compliance-analyses/analysis-1"), response: () => jsonResponse(200, { data: conformeAnalysis("analysis-1", "2026-08-15T10:00:00Z") }) },
      ]),
    );

    render(<InvoiceDetail invoiceId={INVOICE_ID} />);

    await waitFor(() => expect(screen.getByText("Conforme")).toBeInTheDocument());
    expect(screen.getByRole("button", { name: /relancer l'analyse/i })).toBeInTheDocument();
  });

  it("renders a NON_CONFORME finding with its correction link to the customer", async () => {
    vi.mocked(fetch).mockImplementation(
      routeFetch([
        { match: (m, u) => "GET" === m && u.endsWith(`/invoices/${INVOICE_ID}`), response: () => jsonResponse(200, { data: invoiceFixture("ANALYZED") }) },
        { match: (m, u) => "GET" === m && u.endsWith(`/customers/${CUSTOMER_ID}`), response: () => jsonResponse(200, { data: CUSTOMER }) },
        {
          match: (m, u) => "GET" === m && u.includes("compliance-analyses?per_page=1"),
          response: () =>
            jsonResponse(200, {
              data: [analysisSummary("analysis-1", "NON_CONFORME", "2026-08-15T10:00:00Z")],
              meta: { pagination: { page: 1, per_page: 1, total_count: 1, total_pages: 1 } },
            }),
        },
        { match: (m, u) => "GET" === m && u.endsWith("/compliance-analyses/analysis-1"), response: () => jsonResponse(200, { data: nonConformeAnalysis("analysis-1", "2026-08-15T10:00:00Z") }) },
      ]),
    );

    render(<InvoiceDetail invoiceId={INVOICE_ID} />);

    await waitFor(() => expect(screen.getByText(/est absent de cette facture/)).toBeInTheDocument());
    expect(screen.getByRole("link", { name: /corriger maintenant/i })).toHaveAttribute("href", `/customers/${CUSTOMER_ID}`);
  });

  it("shows the fiscal-context-missing business error on a 409, distinct from a technical error", async () => {
    vi.mocked(fetch).mockImplementation(
      routeFetch([
        { match: (m, u) => "GET" === m && u.endsWith(`/invoices/${INVOICE_ID}`), response: () => jsonResponse(200, { data: invoiceFixture("READY_FOR_ANALYSIS") }) },
        { match: (m, u) => "GET" === m && u.endsWith(`/customers/${CUSTOMER_ID}`), response: () => jsonResponse(200, { data: CUSTOMER }) },
        {
          match: (m, u) => "POST" === m && u.endsWith(`/invoices/${INVOICE_ID}/compliance-analyses`),
          response: () =>
            jsonResponse(409, {
              error: {
                code: "HTTP_ERROR",
                message: "Configurez le contexte fiscal de votre organisation avant de lancer une analyse de conformité.",
                details: [],
                request_id: "req-1",
              },
            }),
        },
      ]),
    );

    render(<InvoiceDetail invoiceId={INVOICE_ID} />);

    await waitFor(() => expect(screen.getByRole("button", { name: /^lancer l'analyse$/i })).toBeInTheDocument());
    fireEvent.click(screen.getByRole("button", { name: /^lancer l'analyse$/i }));

    await waitFor(() => expect(screen.getByText(/configurez le contexte fiscal/i)).toBeInTheDocument());
    expect(screen.getByRole("link", { name: /configurer mon entreprise/i })).toHaveAttribute("href", "/company");
  });

  it("sends exactly one POST when the trigger button is clicked twice in a row", async () => {
    const postCalls: string[] = [];
    const deferredResolve: { current: ((response: Response) => void) | null } = { current: null };

    vi.mocked(fetch).mockImplementation(async (input, init) => {
      const url = String(input);
      const method = (init?.method ?? "GET").toUpperCase();
      if ("GET" === method && url.endsWith(`/invoices/${INVOICE_ID}`)) {
        return jsonResponse(200, { data: invoiceFixture("READY_FOR_ANALYSIS") });
      }
      if ("GET" === method && url.endsWith(`/customers/${CUSTOMER_ID}`)) {
        return jsonResponse(200, { data: CUSTOMER });
      }
      if ("POST" === method && url.endsWith(`/invoices/${INVOICE_ID}/compliance-analyses`)) {
        postCalls.push(String(new Headers(init?.headers).get("Idempotency-Key")));
        return new Promise<Response>((resolve) => {
          deferredResolve.current = resolve;
        });
      }
      throw new Error(`Requête non mockée : ${method} ${url}`);
    });

    render(<InvoiceDetail invoiceId={INVOICE_ID} />);

    const button = await screen.findByRole("button", { name: /^lancer l'analyse$/i });
    fireEvent.click(button);
    fireEvent.click(button);

    await waitFor(() => expect(button).toBeDisabled());
    expect(postCalls).toHaveLength(1);

    deferredResolve.current?.(jsonResponse(200, { data: conformeAnalysis("analysis-1", "2026-08-15T10:00:00Z") }));
    await waitFor(() => expect(screen.getByText("Conforme")).toBeInTheDocument());
  });

  it("supports the full stale workflow: banner shown, relaunch, new result replaces the old one", async () => {
    const idempotencyKeys: string[] = [];

    vi.mocked(fetch).mockImplementation(async (input, init) => {
      const url = String(input);
      const method = (init?.method ?? "GET").toUpperCase();

      if ("GET" === method && url.endsWith(`/invoices/${INVOICE_ID}`)) {
        return jsonResponse(200, { data: invoiceFixture("ANALYSIS_STALE") });
      }
      if ("GET" === method && url.endsWith(`/customers/${CUSTOMER_ID}`)) {
        return jsonResponse(200, { data: CUSTOMER });
      }
      if ("GET" === method && url.includes("compliance-analyses?per_page=1")) {
        return jsonResponse(200, {
          data: [analysisSummary("analysis-old", "NON_CONFORME", "2026-08-15T10:00:00Z")],
          meta: { pagination: { page: 1, per_page: 1, total_count: 1, total_pages: 1 } },
        });
      }
      if ("GET" === method && url.endsWith("/compliance-analyses/analysis-old")) {
        return jsonResponse(200, { data: nonConformeAnalysis("analysis-old", "2026-08-15T10:00:00Z") });
      }
      if ("POST" === method && url.endsWith(`/invoices/${INVOICE_ID}/compliance-analyses`)) {
        idempotencyKeys.push(String(new Headers(init?.headers).get("Idempotency-Key")));
        return jsonResponse(200, { data: conformeAnalysis("analysis-new", "2026-08-15T11:00:00Z") });
      }
      throw new Error(`Requête non mockée : ${method} ${url}`);
    });

    render(<InvoiceDetail invoiceId={INVOICE_ID} />);

    await waitFor(() => expect(screen.getByText(/modifiée depuis sa dernière analyse/)).toBeInTheDocument());
    expect(screen.getAllByText("Non conforme").length).toBeGreaterThan(0);

    fireEvent.click(screen.getByRole("button", { name: /relancer l'analyse/i }));

    await waitFor(() => expect(idempotencyKeys).toHaveLength(1));
    const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
    expect(idempotencyKeys[0]).toMatch(uuidPattern);

    await waitFor(() => expect(screen.queryByText(/modifiée depuis sa dernière analyse/)).not.toBeInTheDocument());
    expect(screen.getByText("Conforme")).toBeInTheDocument();
    expect(screen.queryByText("Non conforme")).not.toBeInTheDocument();
  });
});
