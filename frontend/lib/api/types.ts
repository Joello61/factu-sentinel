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

// Phase 4 (docs/08-api-specification.md, sections 26-28 ; docs/07-data-model.md, sections
// 8, 10-11).

export type CustomerType = "PROFESSIONNEL_FRANCAIS" | "PARTICULIER" | "PROFESSIONNEL_ETRANGER";

export interface Customer {
  id: string;
  customer_type: CustomerType;
  name: string;
  siren: string | null;
  vat_number: string | null;
  country: string;
  created_at: string;
  updated_at: string;
}

export interface CreateCustomerPayload {
  customer_type: CustomerType;
  name: string;
  siren?: string | null;
  vat_number?: string | null;
  country: string;
}

export type UpdateCustomerPayload = Partial<CreateCustomerPayload>;

export interface PaginationMeta {
  page: number;
  per_page: number;
  total_count: number;
  total_pages: number;
}

export type OperationType = "VENTE_BIEN" | "PRESTATION_SERVICE" | "MIXTE";

/**
 * ANALYZED/ANALYSIS_STALE ne sont jamais produits avant la Phase 5 (Compliance Engine,
 * inexistant à ce stade) : déclarés ici pour que le type reflète fidèlement le contrat,
 * jamais un simple `string` (../../CLAUDE.md frontend, section 11).
 */
export type InvoiceStatus = "DRAFT" | "READY_FOR_ANALYSIS" | "ANALYZED" | "ANALYSIS_STALE";

export type InvoiceSource = "SAISIE_MANUELLE" | "DOCUMENT_IMPORTE";

export interface InvoiceLine {
  id: string;
  description: string;
  quantity: string;
  unit_price_ht: string;
  vat_rate: string;
  line_amount_ht: string;
  line_amount_vat: string;
  line_amount_ttc: string;
}

export interface Invoice {
  id: string;
  customer_id: string;
  invoice_number: string | null;
  issue_date: string;
  operation_type: OperationType;
  currency: string;
  total_amount_ht: string;
  total_amount_ttc: string;
  vat_exemption_reason: string | null;
  status: InvoiceStatus;
  source: InvoiceSource;
  lines: InvoiceLine[];
  created_at: string;
  updated_at: string;
}

export interface InvoiceLineInputPayload {
  description: string;
  quantity: string;
  unit_price_ht: string;
  vat_rate: string;
}

export interface CreateInvoicePayload {
  customer_id: string;
  operation_type: OperationType;
  issue_date: string;
  currency: string;
  lines: InvoiceLineInputPayload[];
  invoice_number?: string | null;
  vat_exemption_reason?: string | null;
}
