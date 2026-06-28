<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Marketplace,
    Dakword\WBSeller\Exception\ApiTimeRestrictionsException,
    Dakword\WBSeller\Tests\ApiClient\TestCase;
use DateTime;
use InvalidArgumentException;

class MarketplaceTest extends TestCase
{

    public function test_Class()
    {
        $this->assertInstanceOf(Marketplace::class, $this->Marketplace());
    }

    public function test_getSuppliesList()
    {
        $result = $this->Marketplace()->getSuppliesList(500);
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'next'));
        $this->assertTrue(property_exists($result, 'supplies'));

        $this->expectException(InvalidArgumentException::class);
        $this->Marketplace()->getSuppliesList(3000);
    }

    public function test_getSupply()
    {
        $result = $this->Marketplace()->getSupply('WB-GI-123456');
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'NotFound');

        $result1 = $this->Marketplace()->getSuppliesList();
        $result1 = $this->decodeResponse($result1);
        if($result1->supplies) {
            $supply = array_shift($result1->supplies);
            $id = $supply->id;
        $result2 = $this->Marketplace()->getSupply($id);
            $result2 = $this->decodeResponse($result2);
            $this->assertEquals($id, $result2->id);
        }
    }

    public function test_createSupply()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $this->expectException(InvalidArgumentException::class);
        $this->Marketplace()->createSupply(str_repeat('X', 200));
    }

    public function test_deleteSupply()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->deleteSupply('WB-GI-123456');
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'SupplyHasOrders');
    }

    public function test_getSupplyOrders()
    {
        $results = $this->Marketplace()->getSuppliesList();
        $results = $this->decodeResponse($results);
        if ($results->supplies) {
            $supply = array_shift($results->supplies);
            $supplyId = $supply->id;
            $this->assertTrue(property_exists($this->Marketplace()->getSupplyOrders($supplyId), 'orders'));
        } else {
            $this->markTestSkipped('No supplies in account');
        }
    }

    public function test_addSupplyOrder()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->addSupplyOrder('WB-GI-123456', 123456);
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'code'));
        $this->assertEquals($result->code, 'NotFound');
    }

    public function test_closeSupply()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->closeSupply('WB-GI-123456');
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'NotFound');
    }

    public function test_getReShipmentOrdersSupplies()
    {
        $result = $this->Marketplace()->getReShipmentOrdersSupplies();
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'orders'));
    }

    public function test_getSupplyBarcode()
    {
        $results = $this->Marketplace()->getSuppliesList();
        $results = $this->decodeResponse($results);
        if ($results->supplies) {
            $supply = array_shift($results->supplies);
            $supplyId = $supply->id;
            $this->assertTrue(property_exists($this->Marketplace()->getSupplyBarcode($supplyId, 'svg'), 'file'));
            $this->assertTrue(property_exists($this->Marketplace()->getSupplyBarcode($supplyId, 'png'), 'file'));
        }

        $this->expectException(InvalidArgumentException::class);
        $this->Marketplace()->getSupplyBarcode('WB-GI-123456', 'jpg');
        $this->Marketplace()->getSupplyBarcode('WB-GI-123456', 'png');
    }
    
    public function test_cancelOrder()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->cancelOrder(123456);
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'NotFound');
    }

    public function test_gerOrdersStatuses()
    {
        $result1 = $this->Marketplace()->getOrders(10);
        $result1 = $this->decodeResponse($result1);
        if($result1->orders) {
            $ids = array_column($result1->orders, 'id');
        $result2 = $this->Marketplace()->getOrdersStatuses($ids);
            $result2 = $this->decodeResponse($result2);
            $this->assertEquals(count($ids), count($result2->orders));
        } else {
        $result2 = $this->Marketplace()->getOrdersStatuses([]);
            $result2 = $this->decodeResponse($result2);
            $this->assertEquals($result2->code, 'IncorrectRequest');
        }
    }

    public function test_getOrders()
    {
        $result1 = $this->Marketplace()->getOrders(10);
        $result1 = $this->decodeResponse($result1);
        $this->assertTrue(property_exists($result1, 'orders'));

        $date = (new DateTime('2020-01-01'));
        $result2 = $this->Marketplace()->getOrders(20, 0, $date);
        $result2 = $this->decodeResponse($result2);
        $this->assertTrue(property_exists($result2, 'orders'));
        
        $this->expectException(InvalidArgumentException::class);
        $this->Marketplace()->getOrders(2000);
    }

    public function test_getNewOrders()
    {
        $result = $this->Marketplace()->getNewOrders();
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'orders'));
    }

    public function test_setOrderKiz()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->setOrderKiz(123456, []);
        $result = $this->decodeResponse($result);
        $this->assertEquals($result, true);
    }

    public function test_setOrderUin()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->setOrderUin(123456, '1234567890123456');
        $result = $this->decodeResponse($result);
        $this->assertEquals($result, true);
    }

    public function test_setOrderIMEI()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->setOrderIMEI(123456, '123456789012345');
        $result = $this->decodeResponse($result);
        $this->assertEquals($result, true);
    }

    public function test_setOrderGTIN()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->setOrderGTIN(123456, '1234567890123');
        $result = $this->decodeResponse($result);
        $this->assertEquals($result, true);
    }

    public function test_getOrderMeta()
    {
        $result = $this->Marketplace()->getOrderMeta(123456);
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'code'));
        $this->assertEquals($result->code, 'NotFound');
    }

    public function test_deleteOrderMeta()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->deleteOrderMeta(123456, 'uin');
        $result = $this->decodeResponse($result);
        $this->assertFalse($result);

        $this->expectException(InvalidArgumentException::class);
        $result = $this->Marketplace()->deleteOrderMeta(123456, 'ean');
        $result = $this->decodeResponse($result);
    }

    public function test_getOrdersStickers()
    {
        $result = $this->Marketplace()->getOrdersStickers([], 'svg', '40x30');
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'IncorrectRequest');

        $result = $this->Marketplace()->getOrdersStickers([123456], 'svg', '40x30');
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'stickers'));
        
        $this->expectException(InvalidArgumentException::class);
        $this->Marketplace()->getOrdersStickers([12345], 'foo', '40x30');
        $this->expectException(InvalidArgumentException::class);
        $this->Marketplace()->getOrdersStickers([12345], 'png', '30x50');
    }

    public function test_updateWarehouseStocks()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->updateWarehouseStocks(123456, []);
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'IncorrectRequest');
    }

    public function test_deleteWarehouseStocks()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->deleteWarehouseStocks(123456, []);
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'IncorrectRequest');
    }

    public function test_getWarehouseStocks()
    {
        $wareHouses = $this->Marketplace()->Warehouses()->list();
        $wareHouses = $this->decodeResponse($wareHouses);
        $id = $wareHouses ? $wareHouses[0]->id : 123456;
        $result = $this->Marketplace()->getWarehouseStocks($id, ['1234567890']);
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'stocks'));
    }

    public function test_getSupplyBoxes()
    {
        $result = $this->Marketplace()->getSupplyBoxes('WB-GI-1234567');
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'trbxes'));
    }

    public function test_addSupplyBoxes()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Marketplace()->addSupplyBoxes('WB-GI-1234567', 5);
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'FailedToAddSupplyTrbx');
    }

    public function test_deleteSupplyBoxes()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $API = $this->Marketplace();
        $result = $API->deleteSupplyBoxes('WB-GI-0123456', ['WB-TRBX-0123456']);
        $result = $this->decodeResponse($result);
        $this->assertTrue($result);
    }

    public function test_addBoxOrders()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $API = $this->Marketplace();
        $result = $API->addBoxOrders('WB-GI-0123456', 'WB-TRBX-0123456', [123456]);
        $result = $this->decodeResponse($result);
        $this->assertTrue($result);
    }

    public function test_deleteBoxOrder()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $API = $this->Marketplace();
        $result = $API->deleteBoxOrder('WB-GI-0123456', 'WB-TRBX-0123456', 123456);
        $result = $this->decodeResponse($result);
        $this->assertTrue($result);
    }

    public function test_getSupplyBoxStickers()
    {
        $API = $this->Marketplace();
        $result = $API->getSupplyBoxStickers('WB-GI-0123456', ['WB-TRBX-0123456']);
        $result = $this->decodeResponse($result);
        $this->assertEquals($result->code, 'NotFound');
    }

}
