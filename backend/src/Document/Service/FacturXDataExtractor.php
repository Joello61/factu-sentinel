<?php

declare(strict_types=1);

namespace App\Document\Service;

/**
 * Extraction volontairement limitée (docs/06-technical-architecture.md, section 11 :
 * "le MVP n'a pas vocation à reconstruire un parseur complet") : quelques champs
 * directement utiles à préremplir l'Invoice Editor (nom client, identifiant TVA/SIREN,
 * numéro de facture, date, montant total), jamais une structuration complète en
 * InvoiceLine - le résultat de cette classe alimente uniquement
 * DocumentProcessingRecord::extractedDataSummary (suggestion, jamais une écriture directe
 * dans Invoice/Customer, plan Phase 7).
 *
 * XPath par local-name() (pas de namespace figé) : le XML extrait par Mustang peut être une
 * variante CII ancienne (CrossIndustryDocument, ZUGFeRD 1.x) ou récente
 * (CrossIndustryInvoice, EN16931) selon le document source - rester agnostique du schéma
 * précis évite de reconstruire un mapping par version, cohérent avec le périmètre restreint
 * ci-dessus. Best-effort strict : toute absence ou erreur de parsing produit un tableau
 * partiel ou vide, jamais une exception (cette donnée n'est qu'une suggestion UX, jamais une
 * dépendance du Compliance Engine, backend/CLAUDE.md section 5).
 *
 * SEC-DOC-002 : chargée avec LIBXML_NO_XXE (PHP 8.4+/libxml >= 2.13, vérifié au moment de
 * l'implémentation - la substitution d'entités externes est déjà désactivée par défaut
 * depuis libxml 2.9/PHP 8.0, ce flag est une défense en profondeur explicite plutôt qu'une
 * nécessité stricte sur cette version).
 */
final class FacturXDataExtractor
{
    /** @return array<string, string> */
    public function extract(string $xml): array
    {
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            $loaded = $document->loadXML($xml, \LIBXML_NO_XXE);

            if (!$loaded) {
                return [];
            }

            $xpath = new \DOMXPath($document);

            $summary = [];
            $this->addIfPresent($summary, 'invoice_number', $xpath, '//*[local-name()="ExchangedDocument"]//*[local-name()="ID"]');
            $this->addIfPresent($summary, 'issue_date', $xpath, '//*[local-name()="ExchangedDocument"]//*[local-name()="DateTimeString"]');
            $this->addIfPresent($summary, 'buyer_name', $xpath, '//*[local-name()="BuyerTradeParty"]/*[local-name()="Name"]');
            $this->addIfPresent($summary, 'buyer_vat_or_siren', $xpath, '//*[local-name()="BuyerTradeParty"]//*[local-name()="ID"]');
            $this->addIfPresent($summary, 'total_amount', $xpath, '//*[local-name()="GrandTotalAmount"]');

            return $summary;
        } catch (\Throwable) {
            return [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    /** @param array<string, string> $summary */
    private function addIfPresent(array &$summary, string $key, \DOMXPath $xpath, string $query): void
    {
        $nodes = $xpath->query($query);
        if (false === $nodes || 0 === $nodes->length) {
            return;
        }

        $value = trim((string) $nodes->item(0)?->textContent);
        if ('' !== $value) {
            $summary[$key] = $value;
        }
    }
}
