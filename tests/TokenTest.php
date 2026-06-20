<?php

namespace Dakword\WBSeller\Tests;

use Dakword\WBSeller\APIToken;
use Dakword\WBSeller\Exception\WBSellerException;
use Dakword\WBSeller\Tests\ApiClient\TestCase;
use DateTime;

class TokenTest extends TestCase
{
    private $testToken = [
        'exp'    => 1722882872,
        's'      => 1073741832,
        'sid'    => '22e91535-959d-4978-95d0-44c9f0d93d3c',
        'acc'    => 1,
        't'      => false,
        'oid'    => 3931956,
        'access' => [3 => 'Цены и скидки'],
    ];

    private function APIToken()
    {
        return new APIToken(
            base64_encode(json_encode([
                'alg' => 'ES256',
                'typ' => 'JWT',
                'kid' => '20231225v1',
            ]))
            . '.' . base64_encode(json_encode($this->testToken))
            . '.' . 'hash'
        );
    }

    public function test_APIToken()
    {
        $token = $this->APIToken();
        $this->assertInstanceOf(APIToken::class, $token);
    }
    public function test_APITokenException1()
    {
        $this->expectException(WBSellerException::class);
        new APIToken('1.2.3.4');
    }
    public function test_APITokenException2()
    {
        $this->expectException(WBSellerException::class);
        new APIToken('111.22222.33333');
    }

    public function test_getPayload()
    {
        $token = $this->APIToken();
        $payload = $token->getPayload();

        $this->assertTrue(property_exists($payload, 'exp'));
        $this->assertTrue(property_exists($payload, 'acc'));
        $this->assertEquals($this->testToken['sid'], $token->sellerUUID());
    }

    public function test_expireDate()
    {
        $token = $this->APIToken();

        $this->assertInstanceOf(DateTime::class, $token->expireDate());
        $this->assertEquals(
            (new DateTime())->setTimestamp($this->testToken['exp'])->format('Y-m-d H:i:s'),
            $token->expireDate()->format('Y-m-d H:i:s')
        );
    }

    public function test_isExpired()
    {
        $token = $this->APIToken();

        $this->assertTrue($token->isExpired());
    }

    public function test_daysUntilExpiry()
    {
        $token = $this->APIToken();

        $this->assertIsInt($token->daysUntilExpiry());
        $this->assertLessThan(0, $token->daysUntilExpiry());
    }

    public function test_hoursUntilExpiry()
    {
        $token = $this->APIToken();

        $this->assertIsInt($token->hoursUntilExpiry());
        $this->assertLessThan(0, $token->hoursUntilExpiry());
        $this->assertGreaterThanOrEqual($token->daysUntilExpiry() * 24, $token->hoursUntilExpiry());
    }

    public function test_masked()
    {
        $token = $this->APIToken();
        $masked = $token->masked();

        $this->assertStringContainsString('...', $masked);
        $this->assertEquals(13, strlen($masked)); // 5 + 3 + 5
    }

    public function test_tokenType()
    {
        $token = $this->APIToken();

        $this->assertEquals(APIToken::TYPE_BASIC, $token->tokenType());
    }

    public function test_isBasic()
    {
        $token = $this->APIToken();

        $this->assertTrue($token->isBasic());
    }

    public function test_isTest()
    {
        $token = $this->APIToken();

        $this->assertFalse($token->isTest());
    }

    public function test_isPersonal()
    {
        $token = $this->APIToken();

        $this->assertFalse($token->isPersonal());
    }

    public function test_isService()
    {
        $token = $this->APIToken();

        $this->assertFalse($token->isService());
    }

    public function test_serviceId()
    {
        $token = $this->APIToken();
        $this->assertNull($token->serviceId());

        $serviceToken = new APIToken(
            base64_encode(json_encode(['alg' => 'ES256', 'typ' => 'JWT']))
            . '.' . base64_encode(json_encode([
                'exp' => 9999999999,
                's'   => 0,
                'sid' => '22e91535-959d-4978-95d0-44c9f0d93d3c',
                'acc' => APIToken::TYPE_SERVICE,
                't'   => false,
                'for' => 'asid:42',
            ]))
            . '.hash'
        );
        $this->assertEquals('42', $serviceToken->serviceId());
    }

    public function test_isReadOnly()
    {
        $token = $this->APIToken();

        $this->assertTrue($token->isReadOnly());
    }

    public function test_sellerId()
    {
        $token = $this->APIToken();

        $this->assertEquals($this->testToken['oid'], $token->sellerId());
    }

    public function test_sellerUUID()
    {
        $token = $this->APIToken();

        $this->assertEquals($this->testToken['sid'], $token->sellerUUID());
    }

    public function test_accessList()
    {
        $token = $this->APIToken();

        $this->assertEquals($this->testToken['access'], $token->accessList());
    }

    public function test_accessTo()
    {
        $token = $this->APIToken();

        $this->assertTrue($token->accessTo('prices'));
        $this->assertTrue($token->accessTo('common'));
        $this->assertFalse($token->accessTo('chat'));
        $this->assertFalse($token->accessTo('sex'));
    }

    public function test_hasAccess()
    {
        $token = $this->APIToken();

        $this->assertTrue($token->hasAccess('prices'));
        $this->assertTrue($token->hasAccess('prices', 'common'));
        $this->assertFalse($token->hasAccess('prices', 'chat'));
        $this->assertFalse($token->hasAccess('chat', 'content'));
    }
}
