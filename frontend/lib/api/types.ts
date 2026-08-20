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
 * Cycle de vie d'analyse d'une facture (docs/07-data-model.md, section 29). ANALYZED est
 * atteint par App\Compliance\Engine\Service\RunComplianceAnalysisService, ANALYSIS_STALE par
 * une modification de facture après analyse (US-COMPLIANCE-006bis) -- jamais de retour vers
 * DRAFT/READY_FOR_ANALYSIS une fois l'un de ces deux statuts atteint. Déclaré comme union de
 * littéraux, jamais un simple `string` (../../CLAUDE.md frontend, section 11).
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
  // Phase 7 : présent uniquement sur GET /invoices/{id} (docs/08-api-specification.md,
  // section 27) - toujours un tableau, jamais absent (peut être vide).
  documents: DocumentFile[];
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

/**
 * PATCH /invoices/{id} (docs/08-api-specification.md, section 21, 27) : même forme que la
 * création, App\Invoicing\Service\InvoiceService::update() acceptant un payload complet et
 * suivant chaque champ pour décider de la transition ANALYZED -> ANALYSIS_STALE (US-COMPLIANCE-006bis),
 * jamais un diff partiel côté client.
 */
export type UpdateInvoicePayload = CreateInvoicePayload;

// Phase 6 (docs/08-api-specification.md, sections 29-30, 48 ; docs/07-data-model.md,
// sections 16, 18 ; 05-user-stories.md, section 8).

export type ComplianceResult =
  | "CONFORME"
  | "NON_CONFORME"
  | "AVERTISSEMENT"
  | "NON_APPLICABLE"
  | "A_VERIFIER"
  | "INCERTAIN_REGLEMENTAIRE";

export type ComplianceAnalysisStatus = "PENDING" | "RUNNING" | "COMPLETED" | "FAILED";

export type ConfidenceLevel = "ELEVE" | "MOYEN" | "FAIBLE";

/**
 * Bloc `rule` exposé sur chaque ComplianceFinding (docs/08-api-specification.md, section 48) :
 * la version précise de la règle appliquée, jamais « la règle en général » (ADR-003).
 * effective_until reste `null` tant que la RuleVersion est encore active -- la clé est
 * toujours présente, jamais omise.
 */
export interface RuleReference {
  id: string;
  version: number;
  source_reference: string;
  confidence_level: ConfidenceLevel;
  effective_from: string;
  effective_until: string | null;
}

export interface ComplianceFinding {
  id: string;
  result: ComplianceResult;
  message: string;
  related_field: string | null;
  observed_value: string | null;
  correction_action: string | null;
  rule: RuleReference;
}

export interface ComplianceAnalysis {
  id: string;
  invoice_id: string;
  status: ComplianceAnalysisStatus;
  global_result: ComplianceResult | null;
  triggered_at: string;
  completed_at: string | null;
  findings: ComplianceFinding[];
}

/** Forme allégée renvoyée par GET /invoices/{id}/compliance-analyses (liste paginée, sans findings). */
export interface ComplianceAnalysisSummary {
  id: string;
  status: ComplianceAnalysisStatus;
  global_result: ComplianceResult | null;
  triggered_at: string;
  completed_at: string | null;
}

// Phase 8 - AI Assistant (docs/08-api-specification.md, section 35 ; US-AI-001/002).
// "source" est une chaîne fixe renvoyée par le backend (jamais construite côté client) --
// distincte du "rule.source_reference" d'un ComplianceFinding, qui reste la seule source
// réglementaire à proprement parler (../../CLAUDE.md frontend, section 9 : AI Trust &
// Transparency, trois niveaux jamais confondus).

export interface ComplianceFindingExplanation {
  finding_id: string;
  explanation: string;
  source: string;
}

export interface AssistantAnswer {
  question: string;
  answer: string;
  source: string;
}

// Phase 9 (docs/08-api-specification.md, section 33 ; US-DASHBOARD-001). Enum dédié, jamais
// une réutilisation de ComplianceResult -- sémantique différente : ComplianceResult répond
// du résultat d'un finding/d'une règle précise, DashboardGlobalStatus d'un agrégat de
// portefeuille sur plusieurs factures (décision produit Phase 9). AUCUNE_ANALYSE n'est
// jamais confondu avec CONFORME (distingue "rien analysé" de "tout est conforme").

export type DashboardGlobalStatus = "AUCUNE_ANALYSE" | "CONFORME" | "AVERTISSEMENT" | "ATTENTION_REQUISE";

export interface DashboardRecentAnalysis {
  id: string;
  invoice_id: string;
  global_result: ComplianceResult | null;
  triggered_at: string;
}

export interface DashboardRecommendedAction {
  message: string;
  related_analysis_id: string;
}

export interface Dashboard {
  global_status: DashboardGlobalStatus;
  open_issues_count: number;
  warnings_count: number;
  recent_analyses: DashboardRecentAnalysis[];
  recommended_actions: DashboardRecommendedAction[];
}

/**
 * GET /compliance-analyses (docs/08-api-specification.md, section 29 bis - historique
 * organisation-wide, US-HISTORY-001) : même forme que ComplianceAnalysisSummary, avec en
 * plus invoice_id/invoice_number -- indispensable ici, cette liste n'étant jamais déjà
 * scopée à une facture connue de l'appelant (contrairement à
 * GET /invoices/{id}/compliance-analyses).
 */
export interface ComplianceAnalysisHistoryItem {
  id: string;
  invoice_id: string;
  invoice_number: string | null;
  status: ComplianceAnalysisStatus;
  global_result: ComplianceResult | null;
  triggered_at: string;
  completed_at: string | null;
}

// Phase 7 (docs/08-api-specification.md, section 31 ; docs/07-data-model.md, sections
// 13-14). Nommé "DocumentFile", jamais "Document" -- collision avec le type DOM global
// "Document" sinon (../../CLAUDE.md frontend, section 11).

/**
 * PDF_SIMPLE/FACTURX ont un traitement complet ; UBL/CII sont détectés mais non traités
 * cette phase (FAILED/FORMAT_NOT_SUPPORTED, jamais un résultat de conformité) ; INCONNU est
 * une valeur purement défensive côté backend, jamais produite en pratique
 * (App\Document\Enum\DocumentFileFormat).
 */
export type DocumentFileFormat = "PDF_SIMPLE" | "FACTURX" | "UBL" | "CII" | "INCONNU";

export type DocumentProcessingStatus = "UPLOADED" | "PROCESSING" | "PARSED" | "VALIDATED" | "FAILED";

/**
 * Contrat figé avec le backend (App\Document\Enum\DocumentProcessingFailureReason) :
 * FORMAT_NOT_SUPPORTED n'est jamais un jugement sur la validité du fichier, contrairement
 * aux quatre autres valeurs - ne jamais leur donner le même message ni la même catégorie
 * visuelle (../../CLAUDE.md frontend, section 7).
 */
export type DocumentProcessingFailureReason =
  | "FORMAT_NOT_SUPPORTED"
  | "MUSTANG_UNAVAILABLE"
  | "MUSTANG_VALIDATION_FAILED"
  | "INVALID_DOCUMENT"
  | "SECURITY_REJECTED";

/**
 * "suggestions" (jamais "extracted_data"/"data") : rappelle explicitement qu'il ne s'agit
 * jamais d'une vérité métier, seulement des valeurs à proposer à l'utilisateur, qui doit les
 * confirmer/corriger avant toute écriture réelle sur Invoice/Customer (../../CLAUDE.md
 * frontend, section 3 ; invariant central de la Phase 7 côté backend).
 */
export interface DocumentFile {
  id: string;
  invoice_id: string;
  file_name: string;
  file_format: DocumentFileFormat | null;
  file_size: number;
  processing_status: DocumentProcessingStatus;
  failure_reason: DocumentProcessingFailureReason | null;
  suggestions: Record<string, string> | null;
  uploaded_at: string;
  status_url: string;
}
