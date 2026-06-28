<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Supplies;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

/**
 * @coversDefaultClass \Dakword\WBSeller\API\Endpoint\Supplies
 */
class SuppliesTest extends TestCase
{

    public function test_Class()
    {
        $this->assertInstanceOf(Supplies::class, $this->Supplies());
    }

    /**
     * @covers ::ping()
     */
    public function test_ping()
    {
        $result = $this->Supplies()->ping();
        $result = $this->decodeResponse($result);
        $this->assertEquals('OK', $result->Status);
    }

    /**
     * @covers ::coefficients()
     */
    public function test_coefficients()
    {
        $result = $this->Supplies()->coefficients();
        $result = $this->decodeResponse($result);

        $this->assertIsArray($result);
    }

    /**
     * @covers ::options()
     */
    public function test_options()
    {
        $result = $this->Supplies()->options([
            ['quantity' => 1, 'barcode' => '123456']
        ]);
        $result = $this->decodeResponse($result);

        $this->assertTrue(property_exists($result, 'requestId'));
        $this->assertEquals('123456', $result->result[0]->barcode);
    }

    /**
     * @covers ::warehouses()
     */
    public function test_warehouses()
    {
        $result = $this->Supplies()->warehouses();
        $result = $this->decodeResponse($result);

        $this->assertIsArray($result);
        $this->assertTrue(count($result) > 0);
    }
}
