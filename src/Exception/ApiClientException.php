<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Exception;

/**
 * Исключение HTTP-ошибки от WB API.
 *
 * Бросается при получении ошибочного HTTP-статуса: 401 (неверный ключ),
 * 429 (превышен лимит запросов после исчерпания retry-попыток), 504 (таймаут шлюза).
 *
 * Код исключения (`getCode()`) содержит HTTP-статус ответа.
 * Сообщение (`getMessage()`) содержит текст ошибки из тела ответа WB.
 *
 * Подводный камень: при 429 с сообщением "Технический перерыв" бросается
 * ApiTimeRestrictionsException, а не этот класс.
 */
class ApiClientException extends WBSellerException
{

}
