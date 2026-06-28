<?php

namespace Dakword\WBSeller\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Dakword\WBSeller\API;
use Dakword\WBSeller\APIToken;
use Dakword\WBSeller\Enum\ApiName;

class TestCase extends PHPUnitTestCase
{
    private string $promotionApiKey;
    private string $readonlyApiKey;

    public function setUp(): void
    {
        $this->promotionApiKey = (string) getenv('APIKEY');
        $this->readonlyApiKey = (string) getenv('APIKEY_READONLY');
    }

    protected function API(): API
    {
        return $this->createAPI($this->readonlyApiKey);
    }

    protected function PromotionAPI(): API
    {
        return $this->createAPI($this->promotionApiKey);
    }

    private function createAPI(string $apiKey): API
    {
        $options = [
            'masterkey' => $apiKey,
            'locale' => 'ru',
        ];

        $proxy = getenv('PROXY');
        if (is_string($proxy) && $proxy !== '') {
            $options['proxy'] = $proxy;
        }

        return new API($options);
    }

    protected function skipIfNoKeyAPI(ApiName $apiName): void
    {
        $this->skipIfTokenHasNoAccess($this->readonlyApiKey, 'APIKEY_READONLY', $apiName);
    }

    protected function skipIfNoPromotionKeyAPI(): void
    {
        $this->skipIfTokenHasNoAccess($this->promotionApiKey, 'APIKEY', ApiName::ADV);
    }

    private function skipIfTokenHasNoAccess(string $token, string $tokenName, ApiName $apiName): void
    {
        if ($token === '') {
            $this->markTestSkipped(sprintf('%s не задан', $tokenName));
        }

        if (!(new APIToken($token))->accessTo($apiName)) {
            $this->markTestSkipped(sprintf('%s не имеет доступа к API "%s"', $tokenName, $apiName->value));
        }
    }

}
