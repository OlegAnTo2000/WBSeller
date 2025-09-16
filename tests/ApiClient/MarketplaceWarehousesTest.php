<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Subpoint\Warehouses;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class MarketplaceWarehousesTest extends TestCase
{
    private $Warehouses;
    
    public function setUp(): void
    {
        parent::setUp();
        $this->Warehouses = $this->MarketplaceWarehouses();
    }
    public function test_Class()
    {
        $this->assertInstanceOf(Warehouses::class, $this->Marketplace()->Warehouses());
    }

    public function test_list()
    {
        $result = $this->Warehouses->list();
        $this->assertIsArray($result);
        
        $warehouse = array_shift($result);
        $this->assertTrue(property_exists($warehouse, 'id'));
        $this->assertTrue(property_exists($warehouse, 'name'));
        $this->assertTrue(property_exists($warehouse, 'officeId'));
    }

    public function test_offices()
    {
        $result = $this->Warehouses->offices();
        $this->assertIsArray($result);
        
        $office = array_shift($result);
        $this->assertTrue(property_exists($office, 'id'));
        $this->assertTrue(property_exists($office, 'name'));
        $this->assertTrue(property_exists($office, 'address'));
        $this->assertTrue(property_exists($office, 'city'));
        $this->assertTrue(property_exists($office, 'longitude'));
        $this->assertTrue(property_exists($office, 'latitude'));
        $this->assertTrue(property_exists($office, 'selected'));
    }

    public function test_crud()
    {
        $result = $this->Warehouses->offices();
        $office = array_shift($result);

        $warehouse = $this->Warehouses->create('XYZ', $office->id);
        $this->assertTrue(property_exists($warehouse, 'id'));
        
        $updated = $this->Warehouses->update($warehouse->id, 'ABC-Test', $office->id);
        $this->assertTrue($updated);
        
        $deleted = $this->Warehouses->delete($warehouse->id);
        $this->assertTrue($deleted);
    }
}
