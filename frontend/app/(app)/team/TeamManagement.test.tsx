import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { TeamManagement } from "./TeamManagement";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => (body === undefined ? "" : JSON.stringify(body)),
  } as Response;
}

const ORGANIZATION_AS_OWNER = {
  data: { id: "org-1", legal_name: "Atelier Test", configured: true, role: "OWNER", created_at: "2026-08-01T00:00:00+00:00" },
};

const ORGANIZATION_AS_COLLABORATOR = {
  data: { id: "org-1", legal_name: "Atelier Test", configured: true, role: "COLLABORATOR", created_at: "2026-08-01T00:00:00+00:00" },
};

const MEMBERS = {
  data: [
    { id: "membership-1", user_id: "user-1", email: "owner@example.test", role: "OWNER", created_at: "2026-08-01T00:00:00+00:00" },
    { id: "membership-2", user_id: "user-2", email: "collab@example.test", role: "COLLABORATOR", created_at: "2026-08-02T00:00:00+00:00" },
  ],
};

const NO_INVITATIONS = { data: [] };

function mockFetchFor(organization: unknown) {
  return vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
    const url = String(input);
    const method = init?.method ?? "GET";

    if (url.endsWith("/api/v1/organizations/current") && method === "GET") {
      return jsonResponse(200, organization);
    }
    if (url.endsWith("/api/v1/organizations/current/members") && method === "GET") {
      return jsonResponse(200, MEMBERS);
    }
    if (url.endsWith("/api/v1/organizations/current/invitations") && method === "GET") {
      return jsonResponse(200, NO_INVITATIONS);
    }
    if (url.endsWith("/api/v1/organizations/current/invitations") && method === "POST") {
      const body = JSON.parse(String(init?.body));
      return jsonResponse(201, {
        data: { id: "invitation-1", email: body.email, role: body.role, status: "pending", created_at: "2026-08-21T00:00:00+00:00" },
      });
    }

    throw new Error(`Unexpected ${method} to ${url}`);
  });
}

describe("TeamManagement", () => {
  beforeEach(() => {
    vi.stubGlobal("crypto", { randomUUID: () => "00000000-0000-0000-0000-000000000000" });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("lets an OWNER invite a member and shows the new pending invitation", async () => {
    const user = userEvent.setup();
    const fetchMock = mockFetchFor(ORGANIZATION_AS_OWNER);
    vi.stubGlobal("fetch", fetchMock);

    render(<TeamManagement />);

    await waitFor(() => expect(screen.getAllByText("owner@example.test").length).toBeGreaterThan(0));
    expect(screen.getAllByText("collab@example.test").length).toBeGreaterThan(0);
    expect(screen.getByRole("heading", { name: "Inviter un membre" })).toBeInTheDocument();

    await user.type(screen.getByLabelText("Email"), "new-member@example.test");
    await user.click(screen.getByRole("button", { name: "Envoyer l'invitation" }));

    await waitFor(() => expect(screen.getAllByText(/new-member@example\.test/).length).toBeGreaterThan(0));

    const inviteCall = fetchMock.mock.calls.find(
      ([input, init]) => String(input).endsWith("/api/v1/organizations/current/invitations") && init?.method === "POST",
    );
    expect(inviteCall).toBeDefined();
    const [, inviteInit] = inviteCall as [RequestInfo | URL, RequestInit];
    expect(inviteInit.headers).toBeInstanceOf(Headers);
    expect((inviteInit.headers as Headers).get("Idempotency-Key")).toBeTruthy();
    expect(JSON.parse(String(inviteInit.body))).toEqual({ email: "new-member@example.test", role: "COLLABORATOR" });
  });

  it("hides team management actions for a COLLABORATOR", async () => {
    vi.stubGlobal("fetch", mockFetchFor(ORGANIZATION_AS_COLLABORATOR));

    render(<TeamManagement />);

    await waitFor(() => expect(screen.getAllByText("owner@example.test").length).toBeGreaterThan(0));

    expect(screen.queryByRole("heading", { name: "Inviter un membre" })).not.toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Notifier des membres" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Retirer" })).not.toBeInTheDocument();
  });
});
