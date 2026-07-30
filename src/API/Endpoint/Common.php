<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\AbstractEndpoint;
use Dakword\WBSeller\API\Endpoint\Subpoint\News;
use InvalidArgumentException;

class Common extends AbstractEndpoint
{
    protected string $apiName = 'common';

    /**
     * Сервис для получения новостей с портала продавцов.
     */
    public function News(): News
    {
        return new News($this);
    }

    /**
     * Получение информации о продавце
     *
     * Метод позволяет получать наименование продавца и ID его аккаунта.
     * В запросе можно использовать любой токен, у которого не выбрана опция Тестовый контур.
     * Максимум 1 запрос в минуту на один аккаунт продавца
     * @link https://dev.wildberries.ru/openapi/api-information/#tag/Informaciya-o-prodavce/paths/~1api~1v1~1seller-info/get
     */
    public function sellerInfo(): ApiResponse
    {
        return $this->getRequest('/api/v1/seller-info');
    }

    /**
     * Информация о подписке «Джем» продавца.
     */
    public function subscriptions(): ApiResponse
    {
        return $this->getRequest('/api/common/v1/subscriptions');
    }

    /**
     * Подключённые опции и пакеты конструктора тарифов.
     *
     * @param string|null $locale Язык полей ответа: ru или en. По умолчанию используется locale клиента.
     */
    public function tariffConstructorOptions(?string $locale = null): ApiResponse
    {
        $locale ??= $this->locale();
        if (!in_array($locale, ['ru', 'en'], true)) {
            throw new InvalidArgumentException('Недопустимый язык конструктора тарифов: ' . $locale);
        }

        return $this->getRequest('/api/common/v1/tariff-constructor/options', [
            'locale' => $locale,
        ]);
    }
}
