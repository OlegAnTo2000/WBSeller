<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Content;
use Dakword\WBSeller\API\Endpoint\Subpoint\Tags;
use Dakword\WBSeller\Tests\ApiClient\TestCase;
use Dakword\WBSeller\Exception\ApiTimeRestrictionsException;
use InvalidArgumentException;

class ContentTest extends TestCase
{

    private function getCardsList($limit = 10)
    {
        try {
        $result = $this->Content()->getCardsList('', $limit);
            $result = $this->decodeResponse($result);
        } catch (ApiTimeRestrictionsException $exc) {
            $this->markTestSkipped($exc->getMessage());
        }

        $this->assertIsObject($result);
        $this->assertTrue(property_exists($result, 'cards'));

        if (count($result->cards) == 0) {
            $this->markTestSkipped('No cards in account');
        }
        return $result->cards;
    }

    public function test_Class()
    {
        $this->assertInstanceOf(Content::class, $this->Content());
    }

    public function test_getCardsList()
    {
        $limit = 5;
        $Content = $this->Content();

        try {
        $result1 = $Content->getCardsList('', $limit);
            $result1 = $this->decodeResponse($result1);
        } catch (ApiTimeRestrictionsException $exc) {
            $this->markTestSkipped($exc->getMessage());
        }

        $this->assertTrue(property_exists($result1, 'cards'));
        if ($result1->cursor->total == $limit) {
        $result2 = $Content->getCardsList('', $limit, $result1->cursor->updatedAt, $result1->cursor->nmID);
            $result2 = $this->decodeResponse($result2);
            $this->assertTrue(property_exists($result2, 'cursor'));
        }
    }

    public function test_errorCardsList()
    {
        try {
        $result = $this->Content()
                ->getErrorCardsList();
            $result = $this->decodeResponse($result);
        } catch (ApiTimeRestrictionsException $exc) {
            $this->markTestSkipped($exc->getMessage());
        }

        $this->assertIsArray($result->data);
    }

    public function test_getCardByVendorCode()
    {
        $cards = $this->getCardsList(1);
        $card = array_shift($cards);

        $result1 = $this->Content()->getCardByVendorCode($card->vendorCode);
        $result1 = $this->decodeResponse($result1);

        $this->assertTrue(in_array($card->vendorCode, array_column($result1->cards, 'vendorCode')));
    }

    public function test_generateBarcodes()
    {
        try {
        $result = $this->Content()
                ->generateBarcodes(2);
            $result = $this->decodeResponse($result);
        } catch (ApiTimeRestrictionsException $exc) {
            $this->markTestSkipped($exc->getMessage());
        }

        $this->assertCount(2, $result->data);
        $this->assertEquals(13, strlen($result->data[0]));
    }

    public function test_getCardsLimits()
    {
        $result = $this->Content()->getCardsLimits();
        $result = $this->decodeResponse($result);
        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
            $this->assertTrue(property_exists($result->data, 'freeLimits'));
            $this->assertTrue(property_exists($result->data, 'paidLimits'));
        }
    }

    public function test_getTrashList()
    {
        $result = $this->Content()->Trash()->list();
        $result = $this->decodeResponse($result);
        $this->assertTrue(property_exists($result, 'cards'));
        $this->assertTrue(property_exists($result, 'cursor'));
        $this->assertIsArray($result->cards);
    }

    public function test_addCardNomenclature_ERROR()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Content()->addCardNomenclature('TEST', []);
        $result = $this->decodeResponse($result);
        $this->assertEquals('Invalid request format', $result->errorText);
    }

    public function test_createCard_ERROR()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $Content = $this->Content();

        $result1 = $Content->createCard([
            'vendorCode' => 'test',
            'variants' => [],
        ]);
        $result1 = $this->decodeResponse($result1);
        $this->assertTrue($result1->error);
        $this->assertEquals('The request format is incorrect, the number of product items created should not be 0', $result1->errorText);

        $result2 = $Content->createCards([
            [
                'subjectID' => 105,
                'variants' => [[
                    'vendorCode'      => 'test2',
                    'title'           => 'test2',
                    'description'     => 'test2',
                    'brand'           => 'test2',
                    'dimensions'      => [],
                    'characteristics' => [],
                    'sizes'           => [],
                ]],
            ]
        ]);
        $result2 = $this->decodeResponse($result2);
        $this->assertTrue($result2->error);
    }

    public function test_updateCard()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $listCards = $this->getCardsList();
        $listCard = array_shift($listCards);
        $cardsList = $this->Content()->getCardByVendorCode($listCard->vendorCode);
        $cardsList = $this->decodeResponse($cardsList);
        $cards = array_filter($cardsList->cards, fn($card) => $card->vendorCode == $listCard->vendorCode);
        $card = array_shift($cards);
        if($card) {
        $result = $this->Content()->updateCard((array)$card);
            $result = $this->decodeResponse($result);
            $this->assertFalse($result->error);
        } else {
            $this->markTestSkipped('No card found');
        }
    }

    public function test_searchCategory()
    {
        $result = $this->Content()
            ->searchCategory('СекС');
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);
        $this->assertTrue(in_array('Секс куклы', array_column($result->data, 'subjectName')));
    }

    public function test_getParentCategories()
    {
        $result = $this->Content()
            ->getParentCategories();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);
        $this->assertTrue(in_array('Электроника', array_column($result->data, 'name')));
    }

    public function test_getCategoryCharacteristics()
    {
        $result = $this->Content()
            ->getCategoryCharacteristics(105);
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);
        $this->assertTrue(in_array(4, array_column($result->data, 'charcID')));
    }

    public function test_getDirectories()
    {
        $result = $this->Content()
            ->getDirectory('colors');
        $result = $this->decodeResponse($result);

        $this->assertTrue(in_array('черный', array_column($result->data, 'name')));

        $this->expectException(InvalidArgumentException::class);
        $this->Content()->getDirectory('foo');
    }

    public function test_getDirectoryColors()
    {
        $data = $this->Content()->getDirectoryColors()->json();
        $this->assertTrue(in_array('зеленый', array_column($data->data, 'name')));
    }

    public function test_getDirectoryKinds()
    {
        $data = $this->Content()->getDirectoryKinds()->json();
        $this->assertTrue(in_array('Мужской', $data->data));
    }

    public function test_getDirectoryCountries()
    {
        $data = $this->Content()->getDirectoryCountries()->json();
        $this->assertTrue(in_array('Индия', array_column($data->data, 'name')));
    }

    public function test_getDirectorySeasons()
    {
        $data = $this->Content()->getDirectorySeasons()->json();
        $this->assertTrue(in_array('лето', $data->data));
    }

    public function test_getDirectoryTNVED()
    {
        $this->assertFalse($this->Content()->searchDirectoryTNVED(105)->json()->error);
    }

    public function test_moveNms()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Content()->moveNms(123456, [123, 456, 789]);
        $result = $this->decodeResponse($result);

        $this->assertTrue($result->error);
        $this->assertEquals('target imt not found', $result->errorText);
    }

    public function test_removeNms()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Content()->removeNms([123, 456, 789]);
        $result = $this->decodeResponse($result);

        $this->assertTrue($result->error);
        $this->assertEquals('Invalid item card ID specified', $result->errorText);
    }

}
