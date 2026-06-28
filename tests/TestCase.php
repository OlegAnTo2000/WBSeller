<?php

namespace Dakword\WBSeller\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Dakword\WBSeller\API;

class TestCase extends PHPUnitTestCase
{
    private string $apiKey;

    public function setUp(): void
    {
        if (file_exists(__DIR__ . '/../../#KEYS.php')) {
            $this->apiKey = include __DIR__ . '/../../#KEYS.php';
        } else {
            $this->apiKey = getenv('APIKEY');
        }
    }

    protected function API(): API
    {
        $options = [
            'masterkey' => $this->apiKey,
            'locale' => 'ru',
        ];

        $proxy = getenv('PROXY');
        if (is_string($proxy) && $proxy !== '') {
            $options['proxy'] = $proxy;
        }

        return new API($options);
    }

    protected function skipIfNoKeyAPI(): void
    {
        if (empty($this->apiKey)) {
            $this->markTestSkipped('apikey empty');
        }
    }

}
