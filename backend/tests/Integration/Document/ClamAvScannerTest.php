<?php

declare(strict_types=1);

namespace App\Tests\Integration\Document;

use App\Document\Service\ClamAvScanner;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Exception délibérée à la convention habituelle de ce module (backend/CLAUDE.md section
 * 13 : "intégrations externes... en les mockant par défaut", suivie par
 * ExtractDocumentContentHandlerTest pour Mustang) : ce test appelle le vrai service
 * "clamav" (docker-compose.yml) plutôt qu'un double, à la demande explicite de la Phase 17
 * (docs/12-roadmap.md) - un scan antivirus mocké ne prouverait jamais que le câblage
 * réseau/protocole réel détecte quoi que ce soit, ce qui est précisément le risque à
 * couvrir ici (une intégration muette qui ne scanne jamais rien passerait inaperçue avec
 * un double). CreateDocumentControllerTest couvre séparément, avec un double, que le
 * pipeline d'upload respecte bien l'ordre "scan avant stockage" quel que soit le verdict.
 *
 * Constat fait en écrivant ce test, à connaître avant d'en écrire un autre basé sur EICAR :
 * la signature de test ClamAV ne se déclenche que sur un contenu (quasi) strictement égal
 * à la chaîne EICAR standard (un simple retour à la ligne final passe encore, un préfixe ou
 * un suffixe de quelques dizaines d'octets suffit à empêcher la détection - vérifié
 * empiriquement le 26/08/2026 sur clamav/clamav:1.5.2). Une chaîne EICAR noyée dans un PDF
 * ou un XML par ailleurs valides n'est donc **pas** un vecteur de test fiable pour ce
 * moteur - contrairement à une signature de malware réelle, qui est un motif générique
 * recherché n'importe où dans le flux. D'où le contenu strictement égal à la chaîne EICAR
 * utilisé ci-dessous, jamais un habillage PDF/XML.
 */
final class ClamAvScannerTest extends KernelTestCase
{
    private const string EICAR_SIGNATURE = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public function testInfectedContentIsRejected(): void
    {
        self::bootKernel();
        $scanner = self::getContainer()->get(ClamAvScanner::class);

        $this->expectException(UnprocessableEntityHttpException::class);

        $scanner->scan(self::EICAR_SIGNATURE);
    }

    public function testCleanContentIsAccepted(): void
    {
        self::bootKernel();
        $scanner = self::getContainer()->get(ClamAvScanner::class);

        $scanner->scan('%PDF-1.7 - contenu de test parfaitement inoffensif.');

        $this->addToAssertionCount(1);
    }
}
