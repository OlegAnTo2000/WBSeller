<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Feedbacks;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class FeedbacksTest extends TestCase
{

    private $Feedbacks;

    public function setUp(): void
    {
        parent::setUp();

        $this->Feedbacks = $this->Feedbacks();
    }

    public function test_Class()
    {
        $this->assertInstanceOf(Feedbacks::class, $this->Feedbacks());
    }

    public function test_hasNew()
    {
        $result = $this->Feedbacks->hasNew();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result->data, 'hasNewQuestions'));
            $this->assertTrue(property_exists($result->data, 'hasNewFeedbacks'));
        }
    }

    public function test_unansweredCount()
    {
        $result = $this->Feedbacks->unansweredCount();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
            $this->assertTrue(property_exists($result->data, 'countUnanswered'));
            $this->assertTrue(property_exists($result->data, 'countUnansweredToday'));
            $this->assertTrue(property_exists($result->data, 'valuation'));
        }
    }

    public function test_list()
    {
        $result = $this->Feedbacks->list();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
            $this->assertTrue(property_exists($result->data, 'countUnanswered'));
            $this->assertTrue(property_exists($result->data, 'countArchive'));
            $this->assertTrue(property_exists($result->data, 'feedbacks'));
            $this->assertIsArray($result->data->feedbacks);
        }
    }

    public function test_archive()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Feedbacks->archive();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
            $this->assertTrue(property_exists($result->data, 'feedbacks'));
            $this->assertIsArray($result->data->feedbacks);
        }
    }

    public function test_xlsReport()
    {
        $result = $this->Feedbacks->xlsReport();
        $result = $this->decodeResponse($result);
        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
            $this->assertTrue(property_exists($result->data, 'file'));
            $this->assertTrue(property_exists($result->data, 'fileName'));
            $this->assertTrue(property_exists($result->data, 'contentType'));
        }
    }

    public function test_sendAnswer()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Feedbacks->sendAnswer('xxl', 'OK!');
        $result = $this->decodeResponse($result);
        $response = $this->Feedbacks->response();

        $this->assertFalse($result);
        $this->assertTrue($response->error);
        $this->assertEquals('Не найден отзыв xxl', $response->errorText);
    }

    public function test_get()
    {
        $result = $this->Feedbacks->list(1, 10, true);
        $result = $this->decodeResponse($result);

        if(!$result->error) {
            $feedbacks = $result->data->feedbacks;
            if($feedbacks) {
                $feedback = array_shift($feedbacks);
        $result = $this->Feedbacks->get($feedback->id);
                $result = $this->decodeResponse($result);

                $this->assertEquals($feedback->id, $result->data->id);
            } else {
                $this->markTestSkipped('No feedbacks');
            }
        }
    }

    public function test_count()
    {
        $result = $this->Feedbacks->count();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);
        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
        }
    }

    public function test_ratesList()
    {
        $result = $this->Feedbacks->ratesList();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);
        if(!$result->error) {
            $this->assertTrue(property_exists($result->data, 'feedbackValuations'));
            $this->assertTrue(property_exists($result->data, 'productValuations'));
        }
    }

    public function test_rateFeedback()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Feedbacks->rateFeedback('a2X3e4wB-uQDHp63D36M', 1);
        $result = $this->decodeResponse($result);
        $this->assertFalse($result);
    }

    public function test_rateProduct()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Feedbacks->rateProduct('a2X3e4wB-uQDHp63D36M', 1);
        $result = $this->decodeResponse($result);
        $this->assertFalse($result);
    }

}

