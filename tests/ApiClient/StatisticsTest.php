<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Statistics;
use Dakword\WBSeller\Tests\ApiClient\TestCase;
use DateTime;
use InvalidArgumentException;

class StatisticsTest extends TestCase
{

    public function test_Class()
    {
        $this->assertInstanceOf(Statistics::class, $this->Statistics());
    }

    public function test_Incomes()
    {
        $result = $this->Statistics()->incomes(new DateTime());
        $result = $this->decodeResponse($result);
        $this->assertIsArray($result);
    }

    public function test_Stocks()
    {
        $result = $this->Statistics()->stocks(new DateTime());
        $result = $this->decodeResponse($result);
        $this->assertIsArray($result);
    }

    public function test_Orders()
    {
        $statistics = $this->Statistics();
        $statistics->retryOnTooManyRequests();

        $result1 = $statistics->ordersFromDate(new DateTime('2022-10-01'));
        $result1 = $this->decodeResponse($result1);
        $this->assertIsArray($result1);

        $result2 = $statistics->ordersOnDate(new DateTime());
        $result2 = $this->decodeResponse($result2);
        $this->assertIsArray($result2);
    }

    public function test_Sales()
    {
        $statistics = $this->Statistics();
        $statistics->retryOnTooManyRequests();

        $result1 = $statistics->salesFromDate(new DateTime('2022-10-01'));
        $result1 = $this->decodeResponse($result1);
        $this->assertIsArray($result1);
        $result2 = $statistics->salesOnDate(new DateTime('2022-10-20'));
        $result2 = $this->decodeResponse($result2);
        $this->assertIsArray($result2);
    }

    public function test_DetailReport()
    {
        try {
        $result1 = $this->Statistics()->detailReport(new DateTime('2022-10-01'), new DateTime(), 100);
            $result1 = $this->decodeResponse($result1);
            $this->assertIsArray($result1);
        } catch (\Exception $exc) {
            if($exc instanceof \Dakword\WBSeller\Exception\ApiTimeRestrictionsException) {
                $this->assertTrue(true);
            } else {
                throw $exc;
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $this->Statistics()->detailReport(new DateTime('2022-01-01'), new DateTime(), 100_001);
    }

}
