import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router";
import { describe, expect, it } from "vitest";
import { mockApi } from "../test/utils";
import { FriendsPanel } from "./FriendsPanel";

describe("FriendsPanel", () => {
  it("searches people and sends a friend request", async () => {
    const user = userEvent.setup();
    let requested = false;
    const fetchMock = mockApi({
      "GET /api/friends": () => ({ body: { friends: [] } }),
      "GET /api/friends/activity": () => ({ body: { activity: [] } }),
      "GET /api/friend-requests": () => ({
        body: {
          incoming: [],
          outgoing: requested
            ? [
                {
                  id: 8,
                  user_id: 2,
                  first_name: "Bea",
                  last_name: "López",
                  created_at: "",
                },
              ]
            : [],
        },
      }),
      "GET /api/friends/search?q=Bea": () => ({
        body: {
          users: [
            {
              id: 2,
              first_name: "Bea",
              last_name: "López",
              relationship: requested ? "outgoing" : "none",
            },
          ],
        },
      }),
      "POST /api/friend-requests": () => {
        requested = true;
        return {
          status: 201,
          body: { message: "Solicitud de amistad enviada." },
        };
      },
    });

    renderPanel();
    await user.type(
      await screen.findByRole("textbox", { name: "Buscar personas" }),
      "Bea",
    );
    await user.click(screen.getByRole("button", { name: "Buscar" }));
    await user.click(
      await screen.findByRole("button", { name: "Añadir amigo" }),
    );

    expect(
      await screen.findByText("Solicitud de amistad enviada."),
    ).toBeInTheDocument();
    await waitFor(() =>
      expect(fetchMock).toHaveBeenCalledWith(
        "/api/friend-requests",
        expect.objectContaining({ method: "POST" }),
      ),
    );
    expect(await screen.findByText("Solicitud enviada")).toBeInTheDocument();
  });

  it("accepts an incoming request and displays the new friend", async () => {
    const user = userEvent.setup();
    let accepted = false;
    mockApi({
      "GET /api/friends": () => ({
        body: {
          friends: accepted
            ? [{ id: 2, first_name: "Bea", last_name: "López" }]
            : [],
        },
      }),
      "GET /api/friends/activity": () => ({ body: { activity: [] } }),
      "GET /api/friend-requests": () => ({
        body: {
          incoming: accepted
            ? []
            : [
                {
                  id: 8,
                  user_id: 2,
                  first_name: "Bea",
                  last_name: "López",
                  created_at: "",
                },
              ],
          outgoing: [],
        },
      }),
      "POST /api/friend-requests/8/accept": () => {
        accepted = true;
        return { body: { message: "Solicitud aceptada. Ya sois amigos." } };
      },
    });

    renderPanel();
    await user.click(await screen.findByRole("button", { name: "Aceptar" }));

    expect(
      await screen.findByText("Solicitud aceptada. Ya sois amigos."),
    ).toBeInTheDocument();
    expect(
      await screen.findByRole("link", { name: "Bea López" }),
    ).toHaveAttribute("href", "/perfil/2");
    expect(
      screen.getByText("No tienes solicitudes pendientes."),
    ).toBeInTheDocument();
  });

  it("shows a visible status update from a friend", async () => {
    mockApi({
      "GET /api/friends": () => ({
        body: { friends: [{ id: 2, first_name: "Bea", last_name: "López" }] },
      }),
      "GET /api/friends/activity": () => ({
        body: {
          activity: [
            {
              id: 2,
              first_name: "Bea",
              last_name: "López",
              status: "De paseo por Madrid",
              updated_at: "2026-10-23 10:00:00",
            },
          ],
        },
      }),
      "GET /api/friend-requests": () => ({
        body: { incoming: [], outgoing: [] },
      }),
    });

    renderPanel();

    expect(await screen.findByText("De paseo por Madrid")).toBeInTheDocument();
  });
});

function renderPanel() {
  return render(
    <MemoryRouter>
      <FriendsPanel />
    </MemoryRouter>,
  );
}
