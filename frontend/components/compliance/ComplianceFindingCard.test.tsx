import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ComplianceFindingCard, resolveCorrectionLink } from "./ComplianceFindingCard";
import type { ComplianceFinding } from "@/lib/api/types";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as Response;
}

const CONTEXT = { customerId: "customer-1", invoiceId: "invoice-1" };

const SIREN_FINDING: ComplianceFinding = {
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
};

// docs/11-frontend-design-system.md, section 29 : correction toujours résolue à partir du
// contexte déjà chargé par l'appelant, jamais en devinant un identifiant depuis related_field.
describe("resolveCorrectionLink", () => {
  it("routes a customer.* field to the customer edit page", () => {
    expect(resolveCorrectionLink("customer.siren", CONTEXT)).toBe("/customers/customer-1");
  });

  it("routes an invoice.* field to the invoice edit page", () => {
    expect(resolveCorrectionLink("invoice.operation_type", CONTEXT)).toBe("/invoices/invoice-1/edit");
  });

  it("returns no link for an unknown field", () => {
    expect(resolveCorrectionLink("something.else", CONTEXT)).toBeNull();
  });

  it("returns no link when related_field is null", () => {
    expect(resolveCorrectionLink(null, CONTEXT)).toBeNull();
  });
});

// docs/11-frontend-design-system.md, section 28 : niveaux 1-2 toujours visibles, niveau 3
// derrière une divulgation explicite, accessible au clavier.
describe("ComplianceFindingCard", () => {
  it("always shows the badge, message and correction action (levels 1-2)", () => {
    render(<ComplianceFindingCard finding={SIREN_FINDING} context={CONTEXT} />);

    expect(screen.getByText("Non conforme")).toBeInTheDocument();
    expect(screen.getByText(SIREN_FINDING.message)).toBeInTheDocument();
    expect(screen.getByText(SIREN_FINDING.correction_action as string)).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /corriger maintenant/i })).toHaveAttribute(
      "href",
      "/customers/customer-1",
    );
  });

  it("hides rule detail (level 3) until the disclosure is opened", async () => {
    const user = userEvent.setup();
    render(<ComplianceFindingCard finding={SIREN_FINDING} context={CONTEXT} />);

    expect(screen.queryByText("mention-siren-client")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /détails de la règle/i }));

    expect(screen.getByText("mention-siren-client")).toBeInTheDocument();
    expect(screen.getByText("docs/02-regulatory-study.md, section 10")).toBeInTheDocument();
    expect(screen.getByText("Élevée")).toBeInTheDocument();
  });
});

// docs/11-frontend-design-system.md, section 30-31 : déclenchée à la demande, jamais
// automatique ; étiquetée et visuellement distincte du message déterministe ; repli calme
// sur 503, jamais un écran vide ; état dédié sur l'email non vérifié.
describe("ComplianceFindingCard - Expliquer autrement (US-AI-001)", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("shows the AI explanation, labeled distinctly, on success", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, {
        data: {
          finding_id: SIREN_FINDING.id,
          explanation: "Votre client professionnel doit être identifié par son numéro SIREN.",
          source: "Généré par assistance IA à partir du résultat déterministe existant",
        },
      }),
    );

    const user = userEvent.setup();
    render(<ComplianceFindingCard finding={SIREN_FINDING} context={CONTEXT} />);
    await user.click(screen.getByRole("button", { name: /expliquer autrement/i }));

    await waitFor(() => expect(screen.getByText("Explication assistée")).toBeInTheDocument());
    expect(
      screen.getByText("Votre client professionnel doit être identifié par son numéro SIREN."),
    ).toBeInTheDocument();
    // Le message déterministe du finding reste affiché tel quel, jamais remplacé.
    expect(screen.getByText(SIREN_FINDING.message)).toBeInTheDocument();
  });

  it("falls back calmly on a 503, keeping the deterministic message visible", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(503, { error: { code: "HTTP_ERROR", message: "Indisponible", details: [], request_id: null } }),
    );

    const user = userEvent.setup();
    render(<ComplianceFindingCard finding={SIREN_FINDING} context={CONTEXT} />);
    await user.click(screen.getByRole("button", { name: /expliquer autrement/i }));

    await waitFor(() =>
      expect(screen.getByText(/n'est pas disponible pour le moment/i)).toBeInTheDocument(),
    );
    expect(screen.getByText(SIREN_FINDING.message)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /réessayer/i })).toBeInTheDocument();
  });

  it("shows a distinct message when email verification is required", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(403, {
        error: { code: "EMAIL_VERIFICATION_REQUIRED", message: "Vérifiez votre email.", details: [], request_id: null },
      }),
    );

    const user = userEvent.setup();
    render(<ComplianceFindingCard finding={SIREN_FINDING} context={CONTEXT} />);
    await user.click(screen.getByRole("button", { name: /expliquer autrement/i }));

    await waitFor(() => expect(screen.getByText(/vérifiez votre adresse email/i)).toBeInTheDocument());
  });
});
