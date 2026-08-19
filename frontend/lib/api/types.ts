// Formes de réponse alignées sur docs/08-api-specification.md (sections 14-15, 23-24).

export interface ApiErrorDetail {
  field: string;
  issue: string;
}

export interface ApiErrorBody {
  error: {
    code: string;
    message: string;
    details: ApiErrorDetail[];
    request_id: string | null;
  };
}

export function isApiErrorBody(value: unknown): value is ApiErrorBody {
  return (
    typeof value === "object" &&
    value !== null &&
    "error" in value &&
    typeof (value as { error?: unknown }).error === "object"
  );
}

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly details: ApiErrorDetail[];
  readonly requestId: string | null;

  constructor(status: number, body: ApiErrorBody) {
    super(body.error.message);
    this.name = "ApiError";
    this.status = status;
    this.code = body.error.code;
    this.details = body.error.details;
    this.requestId = body.error.request_id;
  }
}

export interface CurrentUser {
  id: string;
  email: string;
  email_verified_at: string | null;
  created_at: string;
}

export interface RegisteredAccount {
  id: string;
  email: string;
}

export interface LoginResult {
  token: string;
}

// Phase 3 (docs/08-api-specification.md, section 24, payload corrigé : plan Phase 3, gap 1 :
// les trois valeurs brutes sont saisies par l'utilisateur, company_size_category est
// toujours dérivé par le backend, jamais accepté en entrée).

export type VatStatus = "ASSUJETTI_REDEVABLE" | "ASSUJETTI_FRANCHISE_EN_BASE" | "NON_ASSUJETTI";

/**
 * Classification à deux niveaux propre au calendrier de la réforme (docs/07-data-model.md,
 * section 7) : pas la classification légale INSEE à quatre niveaux (Micro/PME/ETI/GE).
 */
export type CompanySizeCategory = "GRANDE_ENTREPRISE_ETI" | "PME_TPE_MICRO";

export interface FiscalContext {
  vat_status: VatStatus;
  employees_count: number;
  annual_turnover: string;
  annual_balance_sheet_total: string;
  company_size_category: CompanySizeCategory;
  effective_from: string;
}

export interface Organization {
  id: string;
  legal_name: string | null;
  trade_name: string | null;
  siren: string | null;
  siret: string | null;
  legal_form: string | null;
  country: string | null;
  configured: boolean;
  created_at: string;
  fiscal_context?: FiscalContext;
  eligibility_diagnostic?: EligibilityDiagnostic;
}

export interface UpdateOrganizationPayload {
  legal_name?: string;
  fiscal_context?: {
    vat_status?: VatStatus;
    employees_count?: number;
    annual_turnover?: string;
    annual_balance_sheet_total?: string;
  };
}

export interface EligibilityDiagnostic {
  reception_obligation_date: string | null;
  emission_obligation_date: string | null;
  computed_at: string;
  explanation: string;
}
