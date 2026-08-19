import { ApiError } from "@/lib/api/client";

/**
 * Traduit une ApiError (docs/08-api-specification.md, section 15, format field/issue) en
 * erreurs par champ + un message global pour les cas non rattachables à un champ précis
 * (401, 409, erreurs réseau). Partagé entre les formulaires de connexion et d'inscription
 * (docs/11-frontend-design-system.md, section 19 : composant dès qu'un pattern se répète).
 */
export interface FormErrors {
  fieldErrors: Record<string, string>;
  formError: string | null;
}

export function toFormErrors(error: unknown, fallback: string): FormErrors {
  if (error instanceof ApiError) {
    if (error.details.length > 0) {
      const fieldErrors: Record<string, string> = {};
      for (const detail of error.details) {
        fieldErrors[detail.field] = detail.issue;
      }
      return { fieldErrors, formError: null };
    }
    return { fieldErrors: {}, formError: error.message || fallback };
  }
  return { fieldErrors: {}, formError: fallback };
}
