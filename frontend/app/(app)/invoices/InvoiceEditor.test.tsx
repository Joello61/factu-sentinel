import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { InvoiceEditor } from "./InvoiceEditor";

const pushMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: pushMock }),
}));

function jsonResponse(status: number, body: unknown, headers: Record<string, string> = {}): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    headers: { get: (name: string) => headers[name] ?? null },
    text: async () => JSON.stringify(body),
  } as unknown as Response;
}

const CUSTOMERS_PAGE = {
  data: [
    {
      id: "customer-1",
      customer_type: "PROFESSIONNEL_FRANCAIS",
      name: "Client Test SARL",
      siren: "123456789",
      vat_number: null,
      country: "FR",
      created_at: "2026-08-19T00:00:00Z",
      updated_at: "2026-08-19T00:00:00Z",
    },
  ],
  meta: { pagination: { page: 1, per_page: 100, total_count: 1, total_pages: 1 } },
};

describe("InvoiceEditor", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
    pushMock.mockClear();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  /**
   * REG-005 côté UI (docs/09-test-strategy.md, section 9) : les montants de ligne saisis par
   * l'utilisateur ne sont jamais renvoyés tels quels par le formulaire, seuls
   * description/quantity/unit_price_ht/vat_rate sont soumis (../../../CLAUDE.md frontend,
   * section 8 : le serveur seul calcule les montants).
   */
  it("submits computed-free line payloads and redirects to the created invoice", async () => {
    const user = userEvent.setup();
    let sentBody: Record<string, unknown> | null = null;

    vi.mocked(fetch).mockImplementation(async (_input, init) => {
      const method = init?.method ?? "GET";
      if (method === "GET") {
        return jsonResponse(200, CUSTOMERS_PAGE);
      }
      sentBody = JSON.parse(String(init?.body));
      return jsonResponse(201, {
        data: {
          id: "invoice-1",
          customer_id: "customer-1",
          invoice_number: null,
          issue_date: "2026-08-15",
          operation_type: "PRESTATION_SERVICE",
          currency: "EUR",
          total_amount_ht: "100.00",
          total_amount_ttc: "120.00",
          vat_exemption_reason: null,
          status: "READY_FOR_ANALYSIS",
          source: "SAISIE_MANUELLE",
          lines: [],
          created_at: "2026-08-19T00:00:00Z",
          updated_at: "2026-08-19T00:00:00Z",
        },
      });
    });

    render(<InvoiceEditor />);

    await waitFor(() => expect(screen.getByRole("option", { name: "Client Test SARL" })).toBeInTheDocument());

    await user.selectOptions(screen.getByLabelText(/^client$/i), "customer-1");
    await user.selectOptions(screen.getByLabelText(/nature de l'opération/i), "PRESTATION_SERVICE");
    await user.type(screen.getByLabelText(/date d'émission/i), "2026-08-15");
    await user.clear(screen.getByLabelText(/description/i));
    await user.type(screen.getByLabelText(/description/i), "Prestation A");
    await user.clear(screen.getByLabelText(/prix unitaire ht/i));
    await user.type(screen.getByLabelText(/prix unitaire ht/i), "100");
    await user.click(screen.getByRole("button", { name: /enregistrer la facture/i }));

    await waitFor(() => expect(pushMock).toHaveBeenCalledWith("/invoices/invoice-1"));

    expect(sentBody).not.toBeNull();
    const lines = (sentBody as unknown as { lines: Record<string, unknown>[] }).lines;
    expect(lines).toHaveLength(1);
    expect(Object.keys(lines[0]).sort()).toEqual(["description", "quantity", "unit_price_ht", "vat_rate"]);
  });

  it("maps a 422 validation error on lines to the form", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockImplementation(async (_input, init) => {
      const method = init?.method ?? "GET";
      if (method === "GET") {
        return jsonResponse(200, CUSTOMERS_PAGE);
      }
      return jsonResponse(422, {
        error: {
          code: "VALIDATION_ERROR",
          message: "La requête contient des champs invalides.",
          details: [{ field: "customer_id", issue: "Le client (customer_id) est requis." }],
          request_id: "req-1",
        },
      });
    });

    render(<InvoiceEditor />);

    await waitFor(() => expect(screen.getByRole("option", { name: "Client Test SARL" })).toBeInTheDocument());

    await user.type(screen.getByLabelText(/date d'émission/i), "2026-08-15");
    await user.selectOptions(screen.getByLabelText(/nature de l'opération/i), "PRESTATION_SERVICE");
    await user.clear(screen.getByLabelText(/description/i));
    await user.type(screen.getByLabelText(/description/i), "Prestation A");
    await user.clear(screen.getByLabelText(/prix unitaire ht/i));
    await user.type(screen.getByLabelText(/prix unitaire ht/i), "100");
    await user.click(screen.getByRole("button", { name: /enregistrer la facture/i }));

    await waitFor(() => expect(screen.getByText(/Le client \(customer_id\) est requis/)).toBeInTheDocument());
  });

  describe("edit mode", () => {
    const EDIT_INVOICE_ID = "edit-invoice-1";

    const EXISTING_INVOICE = {
      id: EDIT_INVOICE_ID,
      customer_id: "customer-1",
      invoice_number: "F-2026-042",
      issue_date: "2026-08-10",
      operation_type: "PRESTATION_SERVICE",
      currency: "EUR",
      total_amount_ht: "100.00",
      total_amount_ttc: "120.00",
      vat_exemption_reason: null,
      status: "ANALYZED",
      source: "SAISIE_MANUELLE",
      lines: [
        {
          id: "line-1",
          description: "Prestation existante",
          quantity: "1",
          unit_price_ht: "100.00",
          vat_rate: "0.20",
          line_amount_ht: "100.00",
          line_amount_vat: "20.00",
          line_amount_ttc: "120.00",
        },
      ],
      created_at: "2026-08-10T00:00:00Z",
      updated_at: "2026-08-10T00:00:00Z",
    };

    // docs/08-api-specification.md, section 21 : verrouillage optimiste, ETag capturé au
    // chargement puis renvoyé en If-Match sur le PATCH -- vérifié directement contre le
    // comportement réel de GetInvoiceController/UpdateInvoiceController (backend), pas supposé.
    it("prefills the form from the fetched invoice and its ETag, then PATCHes with If-Match on submit", async () => {
      const user = userEvent.setup();
      let sentIfMatch: string | null = null;
      let sentMethod: string | null = null;

      vi.mocked(fetch).mockImplementation(async (input, init) => {
        const url = String(input);
        const method = (init?.method ?? "GET").toUpperCase();
        if ("GET" === method && url.includes("/customers")) {
          return jsonResponse(200, CUSTOMERS_PAGE);
        }
        if ("GET" === method && url.endsWith(`/invoices/${EDIT_INVOICE_ID}`)) {
          return jsonResponse(200, { data: EXISTING_INVOICE }, { ETag: 'W/"3"' });
        }
        if ("PATCH" === method && url.endsWith(`/invoices/${EDIT_INVOICE_ID}`)) {
          sentMethod = method;
          sentIfMatch = new Headers(init?.headers).get("If-Match");
          return jsonResponse(200, { data: { ...EXISTING_INVOICE, status: "ANALYSIS_STALE" } }, { ETag: 'W/"4"' });
        }
        throw new Error(`Requête non mockée : ${method} ${url}`);
      });

      render(<InvoiceEditor invoiceId={EDIT_INVOICE_ID} />);

      await waitFor(() => expect(screen.getByDisplayValue("Prestation existante")).toBeInTheDocument());
      expect(screen.getByDisplayValue("F-2026-042")).toBeInTheDocument();
      expect(screen.getByRole("heading", { name: /modifier la facture/i })).toBeInTheDocument();

      await user.click(screen.getByRole("button", { name: /enregistrer les modifications/i }));

      await waitFor(() => expect(pushMock).toHaveBeenCalledWith(`/invoices/${EDIT_INVOICE_ID}`));
      expect(sentMethod).toBe("PATCH");
      expect(sentIfMatch).toBe('W/"3"');
    });

    it("shows a distinct conflict message on a 409 (stale ETag), separate from field validation errors", async () => {
      const user = userEvent.setup();

      vi.mocked(fetch).mockImplementation(async (input, init) => {
        const url = String(input);
        const method = (init?.method ?? "GET").toUpperCase();
        if ("GET" === method && url.includes("/customers")) {
          return jsonResponse(200, CUSTOMERS_PAGE);
        }
        if ("GET" === method && url.endsWith(`/invoices/${EDIT_INVOICE_ID}`)) {
          return jsonResponse(200, { data: EXISTING_INVOICE }, { ETag: 'W/"3"' });
        }
        if ("PATCH" === method && url.endsWith(`/invoices/${EDIT_INVOICE_ID}`)) {
          return jsonResponse(409, {
            error: {
              code: "HTTP_ERROR",
              message: "Cette facture a été modifiée depuis sa dernière lecture ; rechargez-la avant de réessayer.",
              details: [],
              request_id: "req-1",
            },
          });
        }
        throw new Error(`Requête non mockée : ${method} ${url}`);
      });

      render(<InvoiceEditor invoiceId={EDIT_INVOICE_ID} />);

      await waitFor(() => expect(screen.getByDisplayValue("Prestation existante")).toBeInTheDocument());
      await user.click(screen.getByRole("button", { name: /enregistrer les modifications/i }));

      await waitFor(() =>
        expect(screen.getByText(/modifiée depuis sa dernière lecture/)).toBeInTheDocument(),
      );
      expect(screen.queryByText(/champs invalides/i)).not.toBeInTheDocument();
    });
  });
});
