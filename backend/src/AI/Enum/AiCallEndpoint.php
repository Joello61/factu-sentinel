<?php

declare(strict_types=1);

namespace App\AI\Enum;

/** docs/07-data-model.md, section 22 : les deux endpoints IA existants depuis la Phase 8. */
enum AiCallEndpoint: string
{
    case EXPLANATION = 'explanation';
    case ASSISTANT_QUESTION = 'assistant_question';
}
