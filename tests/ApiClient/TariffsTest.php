<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Tariffs;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

/**
 * @coversDefaultClass \Dakword\WBSeller\Endpoints\Tariffs
 */
class TariffsTest extends TestCase
{

    public function test_Class()
    {
        $this->assertInstanceOf(Tariffs::class, $this->API()->Tariffs());
    }

    /**
     * @covers ::Box()
     */
    public function test_box()
    {
        $result = $this->API()->Tariffs()->box(new \DateTime());

        $this->assertTrue(property_exists($result, 'dtNextBox'));
        $this->assertTrue(property_exists($result, 'dtTillMax'));
        $this->assertTrue(property_exists($result, 'warehouseList'));
    }

    /**
     * @covers ::Pallet()
     */
    public function test_pallet()
    {
        $result = $this->API()->Tariffs()->pallet(new \DateTime());

        $this->assertTrue(property_exists($result, 'dtNextPallet'));
        $this->assertTrue(property_exists($result, 'dtTillMax'));
        $this->assertTrue(property_exists($result, 'warehouseList'));
    }

    /**
     * @covers ::Return()
     */
    public function test_return()
    {
        $result = $this->API()->Tariffs()->return(new \DateTime());

        $this->assertTrue(property_exists($result, 'dtNextDeliveryDumpKgt'));
        $this->assertTrue(property_exists($result, 'dtNextDeliveryDumpSrg'));
        $this->assertTrue(property_exists($result, 'dtNextDeliveryDumpSup'));
        $this->assertTrue(property_exists($result, 'warehouseList'));
    }

}
