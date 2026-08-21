import { afterEach, describe, expect, it, vi } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { NotificationList } from "./NotificationList";

function jsonResponse(status: number, body: unknown): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    text: async () => JSON.stringify(body),
  } as Response;
}

const NOTIFICATIONS = {
  data: [
    {
      id: "notif-1",
      notification_type: "message_organisation",
      sender_type: "ORGANIZATION_OWNER",
      message: "Bienvenue dans l'équipe.",
      channel: "in_app",
      status: "sent",
      scheduled_for: "2026-08-21T09:00:00+00:00",
      sent_at: "2026-08-21T09:00:00+00:00",
      read_at: null,
    },
  ],
};

describe("NotificationList", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("shows an unread notification and marks it as read on click", async () => {
    const user = userEvent.setup();
    const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      if (url.includes("/api/v1/notifications?") && (init?.method ?? "GET") === "GET") {
        return jsonResponse(200, NOTIFICATIONS);
      }
      if (url.endsWith("/api/v1/notifications/notif-1/read") && init?.method === "PATCH") {
        return jsonResponse(200, { data: { ...NOTIFICATIONS.data[0], read_at: "2026-08-21T10:00:00+00:00" } });
      }
      throw new Error(`Unexpected ${init?.method ?? "GET"} to ${url}`);
    });
    vi.stubGlobal("fetch", fetchMock);

    render(<NotificationList />);

    await waitFor(() => expect(screen.getByText("Bienvenue dans l'équipe.")).toBeInTheDocument());
    expect(screen.getByText("Non lue")).toBeInTheDocument();

    await user.click(screen.getByText("Bienvenue dans l'équipe."));

    await waitFor(() => expect(screen.queryByText("Non lue")).not.toBeInTheDocument());
  });

  it("shows an empty state when there are no notifications", async () => {
    vi.stubGlobal("fetch", vi.fn(async () => jsonResponse(200, { data: [] })));

    render(<NotificationList />);

    await waitFor(() => expect(screen.getByText("Aucune notification pour le moment.")).toBeInTheDocument());
  });
});
