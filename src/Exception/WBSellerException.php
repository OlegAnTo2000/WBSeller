<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Exception;

/**
 * Базовое исключение библиотеки WBSeller.
 *
 * Все собственные исключения пакета наследуют этот класс, что позволяет
 * перехватывать любую ошибку библиотеки одним catch:
 * ```php
 * try {
 *     $api->Content()->cardsList(...);
 * } catch (WBSellerException $e) {
 *     // обработка всех ошибок пакета
 * }
 * ```
 */
class WBSellerException extends \Exception
{

}
