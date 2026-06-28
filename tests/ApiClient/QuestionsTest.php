<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Questions;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class QuestionsTest extends TestCase
{

    private $Questions;

    public function setUp(): void
    {
        parent::setUp();

        $this->Questions = $this->Questions();
    }

    public function test_Class()
    {
        $this->assertInstanceOf(Questions::class, $this->Questions());
    }

    public function test_unansweredCountByPeriod()
    {
        $result = $this->Questions->unansweredCountByPeriod(new \DateTime('2023-07-01'), new \DateTime('2023-07-20 23:59:59'));
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertIsInt($result->data);
        }
    }

    public function test_answeredCountByPeriod()
    {
        $result = $this->Questions->answeredCountByPeriod(new \DateTime('2023-07-01'), new \DateTime('2023-07-20 23:59:59'));
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertIsInt($result->data);
        }
    }

    public function test_unansweredCount()
    {
        $result = $this->Questions->unansweredCount();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
            $this->assertTrue(property_exists($result->data, 'countUnanswered'));
            $this->assertTrue(property_exists($result->data, 'countUnansweredToday'));
        }
    }

    public function test_hasNew()
    {
        $result = $this->Questions->hasNew();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result->data, 'hasNewQuestions'));
            $this->assertTrue(property_exists($result->data, 'hasNewFeedbacks'));
        }
    }

    public function test_list()
    {
        $result = $this->Questions->list();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
            $this->assertTrue(property_exists($result->data, 'countUnanswered'));
            $this->assertTrue(property_exists($result->data, 'countArchive'));
            $this->assertTrue(property_exists($result->data, 'questions'));
            $this->assertIsArray($result->data->questions);
        }
    }

    public function test_xlsReport()
    {
        $result = $this->Questions->xlsReport();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);

        if(!$result->error) {
            $this->assertTrue(property_exists($result, 'data'));
            $this->assertTrue(property_exists($result->data, 'file'));
            $this->assertTrue(property_exists($result->data, 'fileName'));
            $this->assertTrue(property_exists($result->data, 'contentType'));
        }
    }

    public function test_changeViewed()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Questions->changeViewed('xxl', true);
        $result = $this->decodeResponse($result);
        $response = $this->Questions->response();

        $this->assertFalse($result);
        $this->assertTrue($response->error);
        $this->assertEquals('Вопрос не найден', $response->errorText);
    }

    public function test_sendAnswer()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Questions->sendAnswer('xxl', 'OK!');
        $result = $this->decodeResponse($result);
        $response = $this->Questions->response();

        $this->assertFalse($result);
        $this->assertTrue($response->error);
        $this->assertEquals('Вопрос не найден', $response->errorText);
    }

    public function test_reject()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Questions->reject('xxl', 'answer');
        $result = $this->decodeResponse($result);
        $response = $this->Questions->response();

        $this->assertFalse($result);
        $this->assertTrue($response->error);
        $this->assertEquals('Вопрос не найден', $response->errorText);
    }

    public function test_get()
    {
        $result = $this->Questions->list(1, 10, true);
        $result = $this->decodeResponse($result);

        if(!$result->error) {
            $questions = $result->data->questions;
            if($questions) {
                $question = array_shift($questions);
        $result = $this->Questions->get($question->id);
                $result = $this->decodeResponse($result);

                $this->assertEquals($question->id, $result->data->id);
            } else {
                $this->markTestSkipped('No questions');
            }
        }
    }
}
