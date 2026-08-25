import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { NotificationComposer } from "./NotificationComposer";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => (body === undefined ? "" : JSON.stringify(body)),
  } as Response;
}

describe("NotificationComposer", () => {
  beforeEach(() => {
    vi.stubGlobal("crypto", { randomUUID: () => "00000000-0000-0000-0000-000000000000" });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("requires an explicit confirmation before sending, never a direct submit", async () => {
    const user = userEvent.setup();
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      if (url.endsWith("/api/v1/platform-admin/notifications") && init?.method === "POST") {
        return jsonResponse(201, {
          data: { sender_type: "PLATFORM_ADMIN", target_type: "ALL", estimated_recipient_count: 42 },
        });
      }
      throw new Error(`Unexpected fetch to ${url}`);
    });
    vi.stubGlobal("fetch", fetchMock);

    render(<NotificationComposer />);

    await user.selectOptions(screen.getByLabelText("Cible"), "ALL");
    await user.type(screen.getByLabelText("Message"), "Diffusion de test.");
    await user.click(screen.getByRole("button", { name: "Envoyer" }));

    // Le formulaire seul ne doit jamais déclencher l'envoi - une confirmation renforcée est
    // requise pour target_type = ALL (docs/11-frontend-design-system.md, ligne 303).
    expect(fetchMock).not.toHaveBeenCalled();
    const dialog = await screen.findByRole("alertdialog");
    expect(dialog).toHaveTextContent("Diffuser à tous les utilisateurs ?");

    await user.click(screen.getByRole("button", { name: "Diffuser à tous" }));

    await waitFor(() => expect(fetchMock).toHaveBeenCalled());
    const [, init] = fetchMock.mock.calls[0] as [RequestInfo | URL, RequestInit];
    expect(JSON.parse(String(init.body))).toEqual({ target_type: "ALL", message: "Diffusion de test." });
    await screen.findByText(/42 destinataire/);
  });

  it("cancelling the confirmation never sends the notification", async () => {
    const user = userEvent.setup();
    const fetchMock = vi.fn();
    vi.stubGlobal("fetch", fetchMock);

    render(<NotificationComposer />);

    await user.type(screen.getByLabelText("Identifiant de l'utilisateur"), "user-123");
    await user.type(screen.getByLabelText("Message"), "Message individuel.");
    await user.click(screen.getByRole("button", { name: "Envoyer" }));

    await screen.findByRole("alertdialog");
    await user.click(screen.getByRole("button", { name: "Annuler" }));

    expect(fetchMock).not.toHaveBeenCalled();
  });
});
