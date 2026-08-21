import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { SettingsForm } from "./SettingsForm";

const replaceMock = vi.fn();
const logoutMock = vi.fn();

vi.mock("next/navigation", () => ({
  useRouter: () => ({ replace: replaceMock }),
}));

vi.mock("@/components/auth/AuthProvider", () => ({
  useAuth: () => ({ logout: logoutMock }),
}));

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => (body === undefined ? "" : JSON.stringify(body)),
  } as Response;
}

const CURRENT_USER = {
  data: {
    id: "user-1",
    email: "user@example.test",
    email_verified_at: "2026-08-01T00:00:00+00:00",
    created_at: "2026-08-01T00:00:00+00:00",
  },
};

describe("SettingsForm", () => {
  beforeEach(() => {
    vi.stubGlobal("fetch", vi.fn());
    replaceMock.mockClear();
    logoutMock.mockClear();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("submits an email change and shows the re-verification message", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockImplementation(async (_input, init) => {
      const method = init?.method ?? "GET";
      if (method === "GET") return jsonResponse(200, CURRENT_USER);
      if (method === "PATCH") {
        const body = JSON.parse(String(init?.body));
        return jsonResponse(200, {
          data: { ...CURRENT_USER.data, email: body.email, email_verified_at: null },
        });
      }
      throw new Error(`Unexpected ${method}`);
    });

    render(<SettingsForm />);
    await waitFor(() => expect(screen.getByLabelText(/^email$/i)).toHaveValue("user@example.test"));

    await user.clear(screen.getByLabelText(/^email$/i));
    await user.type(screen.getByLabelText(/^email$/i), "new@example.test");
    await user.type(screen.getByLabelText(/mot de passe actuel/i), "a-very-long-password-1234");
    await user.click(screen.getByRole("button", { name: /enregistrer/i }));

    await waitFor(() => expect(screen.getByText(/email de vérification a été envoyé/i)).toBeInTheDocument());
  });

  it("maps a wrong current_password error to the corresponding field", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockImplementation(async (_input, init) => {
      const method = init?.method ?? "GET";
      if (method === "GET") return jsonResponse(200, CURRENT_USER);
      return jsonResponse(422, {
        error: {
          code: "VALIDATION_ERROR",
          message: "La requête contient des champs invalides.",
          details: [{ field: "current_password", issue: "Mot de passe actuel incorrect." }],
          request_id: "req-1",
        },
      });
    });

    render(<SettingsForm />);
    await waitFor(() => expect(screen.getByLabelText(/^email$/i)).toHaveValue("user@example.test"));

    await user.clear(screen.getByLabelText(/^email$/i));
    await user.type(screen.getByLabelText(/^email$/i), "new@example.test");
    await user.type(screen.getByLabelText(/mot de passe actuel/i), "wrong-password");
    await user.click(screen.getByRole("button", { name: /enregistrer/i }));

    await waitFor(() => expect(screen.getByText(/Mot de passe actuel incorrect/)).toBeInTheDocument());
  });

  it("deletes the account and logs out on success", async () => {
    const user = userEvent.setup();
    vi.mocked(fetch).mockImplementation(async (_input, init) => {
      const method = init?.method ?? "GET";
      if (method === "GET") return jsonResponse(200, CURRENT_USER);
      if (method === "DELETE") return jsonResponse(204, undefined);
      throw new Error(`Unexpected ${method}`);
    });

    render(<SettingsForm />);
    await waitFor(() => expect(screen.getByLabelText(/^email$/i)).toHaveValue("user@example.test"));

    await user.click(screen.getByRole("button", { name: /supprimer mon compte/i }));
    const dialog = await screen.findByRole("alertdialog");
    await user.type(within(dialog).getByLabelText(/mot de passe actuel/i), "a-very-long-password-1234");
    await user.click(within(dialog).getByRole("button", { name: /supprimer définitivement mon compte/i }));

    await waitFor(() => expect(logoutMock).toHaveBeenCalled());
    expect(replaceMock).toHaveBeenCalledWith("/login");
  });
});
