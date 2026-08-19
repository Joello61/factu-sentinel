<?php

declare(strict_types=1);

namespace App\Invoicing\Enum;

/**
 * Distingue l'origine d'une Invoice (docs/07-data-model.md, section 10 ; US-INVOICE-001 vs
 * US-INVOICE-002). Phase 4 ne produit jamais DOCUMENT_IMPORTE : ce cas n'existera qu'à partir
 * de la Phase 7 (docs/12-roadmap.md, Document Processing), la valeur est déjà déclarée ici
 * pour ne pas devoir modifier ce type plus tard.
 */
enum InvoiceSource: string
{
    case SAISIE_MANUELLE = 'SAISIE_MANUELLE';
    case DOCUMENT_IMPORTE = 'DOCUMENT_IMPORTE';
}
