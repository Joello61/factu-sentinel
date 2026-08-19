import { describe, expect, it } from "vitest";
import { ApiError } from "@/lib/api/client";
import { toFormErrors } from "./api-error";

describe("toFormErrors", () => {
  it("maps ApiError details to per-field errors", () => {
    const error = new ApiError(422, {
      error: {
        code: "VALIDATION_ERROR",
        message: "La requête contient des champs invalides.",
        details: [
          { field: "email", issue: "Cette valeur n'est pas une adresse email valide." },
          { field: "password", issue: "Cette valeur est trop courte." },
        ],
        request_id: null,
      },
    });

    expect(toFormErrors(error, "fallback")).toEqual({
      fieldErrors: {
        email: "Cette valeur n'est pas une adresse email valide.",
        password: "Cette valeur est trop courte.",
      },
      formError: null,
    });
  });

  it("falls back to a form-level message for an ApiError without field details", () => {
    const error = new ApiError(409, {
      error: { code: "HTTP_ERROR", message: "Un compte existe déjà avec cet email.", details: [], request_id: null },
    });

    expect(toFormErrors(error, "fallback")).toEqual({
      fieldErrors: {},
      formError: "Un compte existe déjà avec cet email.",
    });
  });

  it("uses the fallback message for a non-ApiError (e.g. network failure)", () => {
    expect(toFormErrors(new TypeError("Failed to fetch"), "Impossible de contacter le serveur.")).toEqual({
      fieldErrors: {},
      formError: "Impossible de contacter le serveur.",
    });
  });
});
