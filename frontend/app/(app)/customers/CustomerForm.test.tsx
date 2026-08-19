import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { CustomerForm } from "./CustomerForm";

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

describe("CustomerForm", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
    pushMock.mockClear();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  /**
   * Plan Phase 4, décision D1 : le SIREN n'est jamais requis dans le formulaire, même pour
   * un professionnel français (docs/05-user-stories.md, US-CUSTOMER-002).
   */
  it("submits a PROFESSIONNEL_FRANCAIS customer without a siren", async () => {
    const user = userEvent.setup();
    let sentBody: Record<string, unknown> | null = null;

    vi.mocked(fetch).mockImplementation(async (_input, init) => {
      sentBody = JSON.parse(String(init?.body));
      return jsonResponse(201, {
        data: {
          id: "customer-1",
          customer_type: "PROFESSIONNEL_FRANCAIS",
          name: "Client SARL",
          siren: null,
          vat_number: null,
          country: "FR",
          created_at: "2026-08-19T00:00:00Z",
          updated_at: "2026-08-19T00:00:00Z",
        },
      });
    });

    render(<CustomerForm />);

    await user.selectOptions(screen.getByLabelText(/type de client/i), "PROFESSIONNEL_FRANCAIS");
    await user.type(screen.getByLabelText(/nom ou raison sociale/i), "Client SARL");
    await user.type(screen.getByLabelText(/^pays$/i), "FR");
    await user.click(screen.getByRole("button", { name: /enregistrer/i }));

    await waitFor(() => expect(pushMock).toHaveBeenCalledWith("/customers"));
    expect(sentBody).not.toBeNull();
    expect((sentBody as unknown as { siren: unknown }).siren).toBeNull();
  });

  it("maps a 422 validation error to the corresponding field", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(422, {
        error: {
          code: "VALIDATION_ERROR",
          message: "La requête contient des champs invalides.",
          details: [{ field: "country", issue: "Le pays (country) est requis." }],
          request_id: "req-1",
        },
      }),
    );

    render(<CustomerForm />);

    await user.selectOptions(screen.getByLabelText(/type de client/i), "PARTICULIER");
    await user.type(screen.getByLabelText(/nom ou raison sociale/i), "Jean Dupont");
    await user.click(screen.getByRole("button", { name: /enregistrer/i }));

    await waitFor(() => expect(screen.getByText(/Le pays \(country\) est requis/)).toBeInTheDocument());
  });
});
