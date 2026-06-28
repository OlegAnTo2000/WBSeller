<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Adv;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class AdvTest extends TestCase
{

    private Adv $Adv;

    public function setUp(): void
    {
        parent::setUp();

        $this->Adv = $this->Adv();
    }

    public function test_Class()
    {
        $this->assertInstanceOf(Adv::class, $this->API()->Adv());
    }

    public function test_config()
    {
        $result = $this->Adv->config();
        $result = $this->decodeResponse($result);

        $this->assertIsObject($result);
        $this->assertTrue(property_exists($result, 'config'));
        $this->assertIsArray($result->config);
    }

    public function test_advertsList()
    {
        $result = $this->Adv->advertsList();
        $result = $this->decodeResponse($result);

        $this->assertIsArray($result);
    }

    public function test_start()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Adv->start(123456);
        $result = $this->decodeResponse($result);

        $this->assertFalse($result);
    }

    public function test_pause()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Adv->pause(123456);
        $result = $this->decodeResponse($result);

        $this->assertFalse($result);
    }

    public function test_stop()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Adv->stop(123456);
        $result = $this->decodeResponse($result);

        $this->assertFalse($result);
    }

    public function test_balance()
    {
        $result = $this->Adv->Finances()->balance();
        $result = $this->decodeResponse($result);

        $this->assertIsObject($result);
        $this->assertTrue(property_exists($result, 'balance'));
        $this->assertTrue(property_exists($result, 'net'));
        $this->assertTrue(property_exists($result, 'bonus'));
    }

    public function test_payments()
    {
        $result = $this->Adv->Finances()->payments(new \DateTime('2024-01-01'), new \DateTime('2024-01-31'));
        $result = $this->decodeResponse($result);

        $this->assertIsArray($result);
    }

    public function test_costs()
    {
        $result = $this->Adv->Finances()->costs(new \DateTime('2024-01-01'), new \DateTime('2024-01-31'));
        $result = $this->decodeResponse($result);

        $this->assertIsArray($result);
    }
}
