<?php

declare(strict_types=1);

namespace Dakword\WBSeller\DTOs;

use Dakword\WBSeller\DTOs\Traits\DtoHelperTrait;

/**
 * DTO ответа при создании/сохранении рекламной кампании SearchCatalog (seacat).
 *
 * WB возвращает только идентификатор созданной кампании.
 * Используйте `$dto->id` для дальнейших операций с кампанией.
 *
 * @see \Dakword\WBSeller\API\Endpoint\Subpoint\AdvSearchCatalog
 */
class AdvV2SeacatSaveAdResponseDTO
{
    use DtoHelperTrait;

    /** Идентификатор рекламной кампании SearchCatalog. */
    public int $id;
}