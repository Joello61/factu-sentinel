<?php

declare(strict_types=1);

namespace App\PlatformAdmin\Http;

use App\Organization\Enum\CompanySizeCategory;
use App\Organization\Enum\VatStatus;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * target_criteria (docs/07-data-model.md, section 21 : critères repris de FiscalContext,
 * jamais un champ dupliqué). Champs volontairement typés `list<string>|null` plutôt que
 * `list<VatStatus>|null` - la dénormalisation Symfony d'un tableau d'enum backed sans
 * métadonnées PropertyInfo supplémentaires n'est pas garantie ; la conversion vers les enums
 * réels se fait explicitement dans App\PlatformAdmin\Service\
 * PlatformNotificationRecipientResolver, jamais supposée implicite ici.
 */
final readonly class SendPlatformNotificationTargetCriteria
{
    /**
     * @param list<string>|null $vatStatuses
     * @param list<string>|null $companySizeCategories
     */
    public function __construct(
        #[SerializedName('vat_status')]
        #[Assert\All([new Assert\Choice(callback: [VatStatus::class, 'values'])])]
        public ?array $vatStatuses = null,
        #[SerializedName('company_size_category')]
        #[Assert\All([new Assert\Choice(choices: [
            CompanySizeCategory::GRANDE_ENTREPRISE_ETI->value,
            CompanySizeCategory::PME_TPE_MICRO->value,
        ])])]
        public ?array $companySizeCategories = null,
    ) {
    }
}
