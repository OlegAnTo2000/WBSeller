<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use Dakword\WBSeller\API\AbstractEndpoint;

/**
 * Класс для отправки тестовых запросов к любым URL-адресам
 */
class Test extends AbstractEndpoint
{
    // apiName = '' — валидация прав пропускается намеренно (тестовый endpoint)
}