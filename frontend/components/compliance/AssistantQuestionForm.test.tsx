import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { AssistantQuestionForm } from "./AssistantQuestionForm";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as Response;
}

// docs/08-api-specification.md, section 35 ; US-AI-002. docs/11-frontend-design-system.md,
// section 30 : zone de saisie clairement délimitée du résultat de conformité.
describe("AssistantQuestionForm", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("submits the question and shows the labeled answer", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(200, {
        data: {
          question: "Qu'est-ce qu'un SIREN ?",
          answer: "Le SIREN est un numéro d'identification à 9 chiffres attribué à chaque entreprise.",
          source: "Généré par assistance IA à partir de l'étude réglementaire du produit (02-regulatory-study.md)",
        },
      }),
    );

    const user = userEvent.setup();
    render(<AssistantQuestionForm />);

    await user.type(screen.getByLabelText(/votre question/i), "Qu'est-ce qu'un SIREN ?");
    await user.click(screen.getByRole("button", { name: /demander/i }));

    await waitFor(() => expect(screen.getByText("Explication assistée")).toBeInTheDocument());
    expect(
      screen.getByText("Le SIREN est un numéro d'identification à 9 chiffres attribué à chaque entreprise."),
    ).toBeInTheDocument();

    const [, requestInit] = vi.mocked(fetch).mock.calls[0] as [string, RequestInit];
    expect(JSON.parse(requestInit.body as string)).toEqual({ question: "Qu'est-ce qu'un SIREN ?" });
  });

  it("shows a calm error message on provider unavailability, never a blocking error", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(503, { error: { code: "HTTP_ERROR", message: "Indisponible", details: [], request_id: null } }),
    );

    const user = userEvent.setup();
    render(<AssistantQuestionForm />);

    await user.type(screen.getByLabelText(/votre question/i), "Qu'est-ce qu'un SIREN ?");
    await user.click(screen.getByRole("button", { name: /demander/i }));

    await waitFor(() => expect(screen.getByText(/n'est pas disponible pour le moment/i)).toBeInTheDocument());
  });

  it("shows a distinct message when email verification is required", async () => {
    vi.mocked(fetch).mockResolvedValue(
      jsonResponse(403, {
        error: { code: "EMAIL_VERIFICATION_REQUIRED", message: "Vérifiez votre email.", details: [], request_id: null },
      }),
    );

    const user = userEvent.setup();
    render(<AssistantQuestionForm />);

    await user.type(screen.getByLabelText(/votre question/i), "Qu'est-ce qu'un SIREN ?");
    await user.click(screen.getByRole("button", { name: /demander/i }));

    await waitFor(() => expect(screen.getByText(/vérifiez votre adresse email/i)).toBeInTheDocument());
  });

  it("does not submit an empty question", async () => {
    const user = userEvent.setup();
    render(<AssistantQuestionForm />);

    await user.click(screen.getByRole("button", { name: /demander/i }));

    expect(fetch).not.toHaveBeenCalled();
  });
});
