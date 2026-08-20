<?php

declare(strict_types=1);

namespace App\Tests\Integration\Document;

use App\Document\Entity\Document;
use App\Document\Entity\DocumentProcessingRecord;
use App\Document\Enum\DocumentFileFormat;
use App\Document\Enum\DocumentProcessingFailureReason;
use App\Document\Enum\DocumentProcessingStatus;
use App\Document\Message\ExtractDocumentContentMessage;
use App\Document\MessageHandler\ExtractDocumentContentHandler;
use App\Document\Service\MustangExtractionResult;
use App\Document\Service\StructuredDocumentValidatorInterface;
use App\Customer\Entity\Customer;
use App\Customer\Enum\CustomerType;
use App\Invoicing\Entity\Invoice;
use App\Invoicing\Enum\InvoiceSource;
use App\Invoicing\Enum\OperationType;
use App\Organization\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * App\Document\MessageHandler\ExtractDocumentContentHandler (plan Phase 7). Le
 * StructuredDocumentValidatorInterface réel (Mustang, HTTP) est remplacé par un double de
 * test contrôlable - jamais un appel HTTP réel dans un test d'intégration
 * (backend/CLAUDE.md, section 13 : "intégrations externes... en les mockant par défaut").
 */
final class ExtractDocumentContentHandlerTest extends KernelTestCase
{
    public function testNoXmlEmbeddedProducesPdfSimpleNeverAnError(): void
    {
        [$em, $document, $record, $organizationId] = $this->setUpDocument();
        $validator = new FakeStructuredDocumentValidator();
        $validator->extractResult = MustangExtractionResult::noXmlEmbedded();

        $this->invokeHandler($validator, $document, $record, $organizationId);

        $em->clear();
        $reloadedDocument = $em->find(Document::class, $document->getId());
        $reloadedRecord = $em->find(DocumentProcessingRecord::class, $record->getId());

        self::assertSame(DocumentFileFormat::PDF_SIMPLE, $reloadedDocument->getFileFormat());
        self::assertSame(DocumentProcessingStatus::VALIDATED, $reloadedRecord->getStatus());
        self::assertNull($reloadedRecord->getFailureReason());
        self::assertSame(0, $validator->validateCallCount, 'Aucun appel validate() pour un PDF simple (décision 3, plan Phase 7).');
    }

    /**
     * Correction demandée en revue du plan : une erreur Mustang (timeout/indisponibilité)
     * ne doit JAMAIS devenir PDF_SIMPLE - c'est une branche distincte de "pas de XML trouvé".
     */
    public function testServiceErrorNeverBecomesPdfSimple(): void
    {
        [$em, $document, $record, $organizationId] = $this->setUpDocument();
        $validator = new FakeStructuredDocumentValidator();
        $validator->extractResult = MustangExtractionResult::serviceError();

        $this->invokeHandler($validator, $document, $record, $organizationId);

        $em->clear();
        $reloadedDocument = $em->find(Document::class, $document->getId());
        $reloadedRecord = $em->find(DocumentProcessingRecord::class, $record->getId());

        self::assertNull($reloadedDocument->getFileFormat(), 'file_format ne doit jamais être fixé sur une erreur de service.');
        self::assertSame(DocumentProcessingStatus::FAILED, $reloadedRecord->getStatus());
        self::assertSame(DocumentProcessingFailureReason::MUSTANG_UNAVAILABLE, $reloadedRecord->getFailureReason());
    }

    public function testXmlFoundWithValidationFailureProducesFacturxFailed(): void
    {
        [$em, $document, $record, $organizationId] = $this->setUpDocument();
        $validator = new FakeStructuredDocumentValidator();
        $validator->extractResult = MustangExtractionResult::xmlFound('<xml/>');
        $validator->validateThrows = true;

        $this->invokeHandler($validator, $document, $record, $organizationId);

        $em->clear();
        $reloadedDocument = $em->find(Document::class, $document->getId());
        $reloadedRecord = $em->find(DocumentProcessingRecord::class, $record->getId());

        self::assertSame(DocumentFileFormat::FACTURX, $reloadedDocument->getFileFormat());
        self::assertSame(DocumentProcessingStatus::FAILED, $reloadedRecord->getStatus());
        self::assertSame(DocumentProcessingFailureReason::MUSTANG_VALIDATION_FAILED, $reloadedRecord->getFailureReason());
    }

    public function testUblXmlProducesFormatNotSupportedNeverInvalidDocument(): void
    {
        [$em, $document, $record, $organizationId] = $this->setUpDocument(
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\"><ID>1</ID></Invoice>",
        );
        $validator = new FakeStructuredDocumentValidator();

        $this->invokeHandler($validator, $document, $record, $organizationId);

        $em->clear();
        $reloadedDocument = $em->find(Document::class, $document->getId());
        $reloadedRecord = $em->find(DocumentProcessingRecord::class, $record->getId());

        self::assertSame(DocumentFileFormat::UBL, $reloadedDocument->getFileFormat());
        self::assertSame(DocumentProcessingFailureReason::FORMAT_NOT_SUPPORTED, $reloadedRecord->getFailureReason());
        self::assertSame(0, $validator->extractCallCount, 'UBL/CII ne doit jamais être envoyé à Mustang (décision 2, plan Phase 7).');
    }

    /**
     * Invariant architectural le plus important de la phase (plan Phase 7, revue) : ce
     * handler ne crée jamais d'Invoice/Customer et n'écrit jamais dedans, même quand
     * l'extraction "trouve" des valeurs différentes de celles réellement enregistrées. Un
     * futur changement qui romprait cette frontière (écriture directe depuis ce handler)
     * doit faire échouer ce test explicitement, jamais rester une régression silencieuse.
     */
    public function testHandlerNeverMutatesInvoiceOrCustomer(): void
    {
        [$em, $document, $record, $organizationId] = $this->setUpDocument();
        $invoiceId = $document->getInvoice()->getId();
        $customerId = $document->getInvoice()->getCustomer()->getId();

        $originalCustomerName = $document->getInvoice()->getCustomer()->getName();
        $originalCustomerSiren = $document->getInvoice()->getCustomer()->getSiren();
        $originalInvoiceNumber = $document->getInvoice()->getInvoiceNumber();
        $originalOperationType = $document->getInvoice()->getOperationType();

        $validator = new FakeStructuredDocumentValidator();
        // Suggestion délibérément différente des valeurs réelles ci-dessus : si le handler
        // écrivait quoi que ce soit depuis l'extraction, ce test le détecterait.
        $validator->extractResult = MustangExtractionResult::xmlFound(
            '<CrossIndustryInvoice><ExchangedDocument><ID>SUGGESTED-999</ID></ExchangedDocument>'
            .'<SupplyChainTradeTransaction><ApplicableHeaderTradeAgreement><BuyerTradeParty>'
            .'<Name>Nom Suggéré Différent</Name><ID>999999999</ID></BuyerTradeParty>'
            .'</ApplicableHeaderTradeAgreement></SupplyChainTradeTransaction></CrossIndustryInvoice>',
        );

        $this->invokeHandler($validator, $document, $record, $organizationId);

        $em->clear();
        /** @var Invoice $reloadedInvoice */
        $reloadedInvoice = $em->find(Invoice::class, $invoiceId);
        /** @var Customer $reloadedCustomer */
        $reloadedCustomer = $em->find(Customer::class, $customerId);

        self::assertSame($originalCustomerName, $reloadedCustomer->getName());
        self::assertSame($originalCustomerSiren, $reloadedCustomer->getSiren());
        self::assertSame($originalInvoiceNumber, $reloadedInvoice->getInvoiceNumber());
        self::assertSame($originalOperationType, $reloadedInvoice->getOperationType());
    }

    /**
     * Invariant d'idempotence (plan Phase 7, correction demandée) : une redélivrance
     * Messenger du même message, sur une tentative déjà terminale, est un no-op strict.
     */
    public function testRedeliveredMessageOnTerminalRecordIsNoOp(): void
    {
        [$em, $document, $record, $organizationId] = $this->setUpDocument();
        $validator = new FakeStructuredDocumentValidator();
        $validator->extractResult = MustangExtractionResult::noXmlEmbedded();

        $this->invokeHandler($validator, $document, $record, $organizationId);
        self::assertSame(1, $validator->extractCallCount);

        // Redélivrance : même message, même DocumentProcessingRecord déjà VALIDATED.
        $this->invokeHandler($validator, $document, $record, $organizationId);

        self::assertSame(1, $validator->extractCallCount, 'Aucun second appel Mustang sur redélivrance.');

        $em->clear();
        $processingRecords = $em->getRepository(DocumentProcessingRecord::class)->findBy(['document' => $document->getId()]);
        self::assertCount(1, $processingRecords, 'Aucun second DocumentProcessingRecord créé par la redélivrance.');
    }

    /**
     * Concurrence réelle (plan Phase 7, correction demandée - même exigence que
     * App\Tests\Integration\Shared\IdempotencyStoreTest::testConcurrentReservationBlocksUntilFirstTransactionCommits) :
     * un second process PHP, connexion PostgreSQL séparée, doit bloquer sur son propre
     * SELECT ... FOR UPDATE tant que la transaction principale ne committe pas.
     */
    public function testConcurrentClaimBlocksUntilFirstTransactionCommits(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();

        $organization = new Organization();
        $customer = new Customer($organization, CustomerType::PROFESSIONNEL_FRANCAIS, 'Client Test', '123456789', null, 'FR');
        $invoice = new Invoice($organization, $customer, null, new \DateTimeImmutable('2026-08-20'), OperationType::PRESTATION_SERVICE, 'EUR', null, InvoiceSource::SAISIE_MANUELLE);
        $document = new Document($organization, $invoice, 'test.pdf', 1000, 'checksum', 'storage-ref');
        $record = new DocumentProcessingRecord($document);
        $document->setCurrentProcessingRecord($record);

        $em->persist($organization);
        $em->persist($customer);
        $em->persist($invoice);
        $em->persist($document);
        $em->persist($record);
        $em->flush();

        $recordId = $record->getId()->toRfc4122();

        $childScript = <<<'PHP'
            <?php
            [$host, $port, $dbname, $user, $password, $recordId] = array_slice($argv, 1);
            $pdo = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname), $user, $password);
            $started = microtime(true);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, status FROM document_processing_records WHERE id = ? FOR UPDATE');
            $stmt->execute([$recordId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $elapsedMs = (int) ((microtime(true) - $started) * 1000);
            fwrite(STDOUT, json_encode(['status' => $row['status'] ?? null, 'elapsed_ms' => $elapsedMs]));
            $pdo->commit();
            PHP;

        $childScriptPath = tempnam(sys_get_temp_dir(), 'claim_child_');
        self::assertIsString($childScriptPath);
        file_put_contents($childScriptPath, $childScript);

        $connection = $em->getConnection();
        $connection->beginTransaction();
        // Verrou pessimiste (même mécanisme que App\Document\Repository\
        // DocumentProcessingRecordRepository::findForUpdate() - reproduit ici en SQL brut
        // pour ne pas dépendre du cycle de vie de l'EntityManager pendant le proc_open()).
        $connection->executeQuery('SELECT id FROM document_processing_records WHERE id = ? FOR UPDATE', [$recordId]);

        $process = proc_open(
            ['php', $childScriptPath, (string) $params['host'], (string) $params['port'], (string) $params['dbname'], (string) $params['user'], (string) $params['password'], $recordId],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        usleep(300_000);
        $status = proc_get_status($process);
        self::assertTrue($status['running'], 'Le process concurrent doit être bloqué sur son propre SELECT ... FOR UPDATE.');

        $connection->commit();

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        unlink($childScriptPath);

        self::assertIsString($output);
        $result = json_decode($output, true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('UPLOADED', $result['status'], 'Après déblocage, le process concurrent doit lire le statut réel (jamais modifié par la transaction principale ici, qui ne fait que verrouiller).');
        // Seuil légèrement inférieur au usleep() ci-dessus (300ms) plutôt qu'égal : le
        // chronométrage du script enfant démarre avant même l'établissement de sa propre
        // connexion PDO, dont le coût peut légitimement grignoter quelques dizaines de
        // millisecondes sans que cela remette en cause la preuve du blocage réel (une requête
        // non bloquée sur ce même schéma prend un ordre de grandeur de moins, quelques ms).
        self::assertGreaterThanOrEqual(200, $result['elapsed_ms'], 'Le process concurrent doit avoir réellement attendu le commit.');
    }

    /**
     * @return array{0: EntityManagerInterface, 1: Document, 2: DocumentProcessingRecord, 3: \Symfony\Component\Uid\Uuid}
     */
    private function setUpDocument(?string $content = null): array
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);

        $organization = new Organization();
        $customer = new Customer($organization, CustomerType::PROFESSIONNEL_FRANCAIS, 'Client Test', '123456789', null, 'FR');
        $invoice = new Invoice($organization, $customer, null, new \DateTimeImmutable('2026-08-20'), OperationType::PRESTATION_SERVICE, 'EUR', null, InvoiceSource::SAISIE_MANUELLE);

        $storage = $container->get(\App\Shared\Storage\StorageInterface::class);
        $fileContent = $content ?? "%PDF-1.4\n%fake-test-content";
        $reference = $storage->store($fileContent);

        $document = new Document($organization, $invoice, 'test.pdf', \strlen($fileContent), 'checksum', $reference);
        $record = new DocumentProcessingRecord($document);
        $document->setCurrentProcessingRecord($record);

        $em->persist($organization);
        $em->persist($customer);
        $em->persist($invoice);
        $em->persist($document);
        $em->persist($record);
        $em->flush();

        return [$em, $document, $record, $organization->getId()];
    }

    private function invokeHandler(StructuredDocumentValidatorInterface $validator, Document $document, DocumentProcessingRecord $record, \Symfony\Component\Uid\Uuid $organizationId): void
    {
        $container = self::getContainer();
        $handler = new ExtractDocumentContentHandler(
            $container->get(EntityManagerInterface::class),
            $container->get(\App\Document\Repository\DocumentRepository::class),
            $container->get(\App\Document\Repository\DocumentProcessingRecordRepository::class),
            $validator,
            $container->get(\App\Shared\Storage\StorageInterface::class),
            $container->get(\App\Document\Service\FacturXDataExtractor::class),
        );

        $handler(new ExtractDocumentContentMessage($document->getId(), $record->getId(), $organizationId));
    }
}

/** Double de test contrôlable pour StructuredDocumentValidatorInterface (Mustang, HTTP réel jamais appelé en test d'intégration). */
final class FakeStructuredDocumentValidator implements StructuredDocumentValidatorInterface
{
    public MustangExtractionResult $extractResult;
    public bool $validateThrows = false;
    public int $extractCallCount = 0;
    public int $validateCallCount = 0;

    public function __construct()
    {
        $this->extractResult = MustangExtractionResult::noXmlEmbedded();
    }

    public function extract(string $content): MustangExtractionResult
    {
        ++$this->extractCallCount;

        return $this->extractResult;
    }

    public function validate(string $content): string
    {
        ++$this->validateCallCount;

        if ($this->validateThrows) {
            throw new \RuntimeException('Simulated Mustang validation failure.');
        }

        return '<validation/>';
    }
}
