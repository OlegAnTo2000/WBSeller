<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Tests\Unit;

use Dakword\WBSeller\API\Endpoint\Adv;
use Dakword\WBSeller\API\Endpoint\Content;
use Dakword\WBSeller\Exception\LocalTokenValidationException;
use Dakword\WBSeller\Tests\TestCase;

class IntegrationTokenSelectionTest extends TestCase
{
    private string|false $originalApiKey;
    private string|false $originalApiKeyReadonly;

    public function setUp(): void
    {
        $this->originalApiKey = getenv('APIKEY');
        $this->originalApiKeyReadonly = getenv('APIKEY_READONLY');

        putenv('APIKEY=' . $this->tokenWithAccessTo(6));
        putenv('APIKEY_READONLY=' . $this->tokenWithAccessTo(1, 30));

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->restoreEnvironmentVariable('APIKEY', $this->originalApiKey);
        $this->restoreEnvironmentVariable('APIKEY_READONLY', $this->originalApiKeyReadonly);
    }

    public function testReadonlyTokenIsUsedForRegularApi(): void
    {
        $this->assertInstanceOf(Content::class, $this->API()->Content());
    }

    public function testPromotionTokenIsUsedForAdvApi(): void
    {
        $this->assertInstanceOf(Adv::class, $this->PromotionAPI()->Adv());
    }

    public function testReadonlyTokenIsNotUsedForAdvApi(): void
    {
        $this->expectException(LocalTokenValidationException::class);

        $this->API()->Adv();
    }

    public function testPromotionTokenIsNotUsedForRegularApi(): void
    {
        $this->expectException(LocalTokenValidationException::class);

        $this->PromotionAPI()->Content();
    }

    private function tokenWithAccessTo(int ...$positions): string
    {
        $mask = array_reduce(
            $positions,
            static fn(int $mask, int $position): int => $mask | (1 << $position),
            0,
        );
        $payload = json_encode([
            'exp' => time() + 3600,
            's' => $mask,
            'sid' => '00000000-0000-0000-0000-000000000000',
            'acc' => 3,
            't' => false,
        ], JSON_THROW_ON_ERROR);

        return 'header.' . $this->base64UrlEncode($payload) . '.signature';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function restoreEnvironmentVariable(string $name, string|false $value): void
    {
        putenv($value === false ? $name : $name . '=' . $value);
    }
}
