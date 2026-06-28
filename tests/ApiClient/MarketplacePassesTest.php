<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Subpoint\Passes;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class MarketplacePassesTest extends TestCase
{
    private $Passes;
    
    public function setUp(): void
    {
        parent::setUp();
        $this->Passes = $this->MarketplacePasses();
    }
    public function test_Class()
    {
        $this->assertInstanceOf(Passes::class, $this->Marketplace()->Passes());
    }
    public function test_list()
    {
        $result = $this->Passes->list();
        $result = $this->decodeResponse($result);
        $this->assertIsArray($result);
        if($result) {
            $pass = array_shift($result);
            $this->assertTrue(property_exists($pass, 'id'));
            $this->assertTrue(property_exists($pass, 'officeId'));
            $this->assertTrue(property_exists($pass, 'dateEnd'));
        }
    }

    public function test_offices()
    {
        $result = $this->Passes->offices();
        $result = $this->decodeResponse($result);
        $this->assertIsArray($result);
        
        $office = array_shift($result);
        $this->assertTrue(property_exists($office, 'id'));
        $this->assertTrue(property_exists($office, 'name'));
        $this->assertTrue(property_exists($office, 'address'));
    }

    public function test_crud()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Passes->offices();
        $result = $this->decodeResponse($result);
        $office = array_shift($result);

        $pass = $this->Passes->create($office->id, 'Газелька', 'X999XX99', 'Имя', 'Фамилия');
        $this->assertTrue(property_exists($pass, 'id'));
        
        $updated = $this->Passes->update($pass->id, $office->id, 'Газель', 'О777ММ77', 'Водитель', 'Газели');
        $updated = $this->decodeResponse($updated);
        $this->assertTrue($updated);
        
        $deleted = $this->Passes->delete($pass->id);
        $deleted = $this->decodeResponse($deleted);
        $this->assertTrue($deleted);
    }
}
