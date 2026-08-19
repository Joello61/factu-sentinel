import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { CompanyForm } from "./CompanyForm";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as Response;
}

const NOT_CONFIGURED = {
  data: {
    id: "org-1",
    legal_name: null,
    trade_name: null,
    siren: null,
    siret: null,
    legal_form: null,
    country: null,
    configured: false,
    created_at: "2026-08-19T00:00:00+00:00",
  },
};

describe("CompanyForm", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("submits the three raw fiscal values and displays the returned diagnostic", async () => {
    const user = userEvent.setup();
    let sentBody: { fiscal_context?: Record<string, unknown> } | null = null;
    vi.mocked(fetch).mockImplementation(async (input, init) => {
      const method = init?.method ?? "GET";
      if (method === "GET") return jsonResponse(200, NOT_CONFIGURED);
      if (method === "PATCH") {
        const body = JSON.parse(String(init?.body));
        sentBody = body;
        return jsonResponse(200, {
          data: {
            ...NOT_CONFIGURED.data,
            legal_name: body.legal_name,
            configured: true,
            fiscal_context: {
              vat_status: body.fiscal_context.vat_status,
              employees_count: body.fiscal_context.employees_count,
              annual_turnover: body.fiscal_context.annual_turnover,
              annual_balance_sheet_total: body.fiscal_context.annual_balance_sheet_total,
              company_size_category: "PME_TPE_MICRO",
              effective_from: "2026-08-19",
            },
            eligibility_diagnostic: {
              reception_obligation_date: "2026-09-01",
              emission_obligation_date: "2027-09-01",
              computed_at: "2026-08-19T12:00:00+00:00",
              explanation: "Vous bénéficiez de la franchise en base de TVA.",
            },
          },
        });
      }
      throw new Error(`Unexpected ${method} to ${String(input)}`);
    });

    render(<CompanyForm />);
    await waitFor(() => expect(screen.getByLabelText(/raison sociale/i)).toBeInTheDocument());

    await user.type(screen.getByLabelText(/raison sociale/i), "Atelier Test SARL");
    await user.selectOptions(screen.getByLabelText(/statut TVA/i), "ASSUJETTI_FRANCHISE_EN_BASE");
    await user.type(screen.getByLabelText(/effectif salarié/i), "5");
    await user.type(screen.getByLabelText(/chiffre d'affaires annuel/i), "200000");
    await user.type(screen.getByLabelText(/total du bilan annuel/i), "150000");
    await user.click(screen.getByRole("button", { name: /enregistrer/i }));

    // La regex doit rester assez spécifique pour ne pas matcher aussi l'option du <select>
    // "Assujetti, en franchise en base de TVA" (docs/11-frontend-design-system.md, section
    // 22 : le libellé de statut réutilise volontairement ce même vocabulaire).
    await waitFor(() => expect(screen.getByText(/Vous bénéficiez de la franchise en base de TVA/)).toBeInTheDocument());
    expect(sentBody?.fiscal_context?.company_size_category).toBeUndefined();
  });

  it("maps a 422 validation error to the corresponding field", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockImplementation(async (_input, init) => {
      const method = init?.method ?? "GET";
      if (method === "GET") return jsonResponse(200, NOT_CONFIGURED);
      return jsonResponse(422, {
        error: {
          code: "VALIDATION_ERROR",
          message: "La requête contient des champs invalides.",
          details: [{ field: "annual_balance_sheet_total", issue: "Le total du bilan annuel est requis." }],
          request_id: "req-1",
        },
      });
    });

    render(<CompanyForm />);
    await waitFor(() => expect(screen.getByLabelText(/raison sociale/i)).toBeInTheDocument());

    await user.type(screen.getByLabelText(/raison sociale/i), "Atelier Test SARL");
    await user.selectOptions(screen.getByLabelText(/statut TVA/i), "ASSUJETTI_REDEVABLE");
    await user.type(screen.getByLabelText(/effectif salarié/i), "5");
    await user.type(screen.getByLabelText(/chiffre d'affaires annuel/i), "200000");
    await user.click(screen.getByRole("button", { name: /enregistrer/i }));

    await waitFor(() => expect(screen.getByText(/Le total du bilan annuel est requis/)).toBeInTheDocument());
  });
});
