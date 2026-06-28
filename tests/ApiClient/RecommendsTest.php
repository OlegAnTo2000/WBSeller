<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Recommends;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class RecommendationsTest extends TestCase
{

    public function test_Class()
    {
        $this->assertInstanceOf(Recommends::class, $this->Recommends());
    }

    public function test_list()
    {
        $nmIds = $this->getRealNms(2);
        $result = $this->Recommends()->list($nmIds);
        $result = $this->decodeResponse($result);
        $this->assertIsObject($result);
        $this->assertTrue(property_exists($result, 'data'));
    }

    public function test_add()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $recom = $this->Recommends();
        $response = $recom->add([123456 => [12345, 67890]]);
        $this->assertEquals(200, $response->statusCode);
    }

    public function test_delete()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $recom = $this->Recommends();
        $response = $recom->delete([123456 => [12345, 67890]]);
        $this->assertEquals(200, $response->statusCode);
    }

    public function test_update()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $recom = $this->Recommends();
        $response = $recom->update([123456 => [12345, 67890]]);
        $this->assertEquals(200, $response->statusCode);
    }

}
