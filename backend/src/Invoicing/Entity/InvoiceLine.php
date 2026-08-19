<?php

declare(strict_types=1);

namespace App\Invoicing\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Possédée par Invoice (docs/07-data-model.md, section 11 ; agrégat section 27) : aucun
 * cycle de vie indépendant, aucun endpoint séparé (docs/08-api-specification.md, section 19).
 * `line_amount_ht`/`line_amount_vat`/`line_amount_ttc` sont toujours calculés côté serveur
 * par App\Invoicing\Service\InvoiceAmountCalculator, jamais acceptés depuis le client.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoice_lines')]
#[ORM\Index(name: 'idx_invoice_lines_invoice_id', columns: ['invoice_id'])]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'invoice_id', nullable: false)]
    private Invoice $invoice;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 3)]
    private string $quantity;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $unitPriceHt;

    /** Fraction décimale (0.20 = 20 %), jamais un pourcentage entier (docs/07-data-model.md, section 12). */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 4)]
    private string $vatRate;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $lineAmountHt;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $lineAmountVat;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    private string $lineAmountTtc;

    public function __construct(
        Invoice $invoice,
        string $description,
        string $quantity,
        string $unitPriceHt,
        string $vatRate,
        string $lineAmountHt,
        string $lineAmountVat,
        string $lineAmountTtc,
    ) {
        $this->id = Uuid::v7();
        $this->invoice = $invoice;
        $this->description = $description;
        $this->quantity = $quantity;
        $this->unitPriceHt = $unitPriceHt;
        $this->vatRate = $vatRate;
        $this->lineAmountHt = $lineAmountHt;
        $this->lineAmountVat = $lineAmountVat;
        $this->lineAmountTtc = $lineAmountTtc;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getUnitPriceHt(): string
    {
        return $this->unitPriceHt;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function getLineAmountHt(): string
    {
        return $this->lineAmountHt;
    }

    public function getLineAmountVat(): string
    {
        return $this->lineAmountVat;
    }

    public function getLineAmountTtc(): string
    {
        return $this->lineAmountTtc;
    }
}
