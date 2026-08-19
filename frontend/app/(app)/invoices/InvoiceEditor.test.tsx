import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { InvoiceEditor } from "./InvoiceEditor";

const pushMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: pushMock }),
}));

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as Response;
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
});
