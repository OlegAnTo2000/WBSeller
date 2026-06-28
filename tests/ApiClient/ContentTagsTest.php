<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Subpoint\Tags;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class ContentTagsTest extends TestCase
{
    private $Tags;
    
    public function setUp(): void
    {
        parent::setUp();
        $this->Tags = $this->ContentTags();
    }
    public function test_Class()
    {
        $this->assertInstanceOf(Tags::class, $this->Content()->Tags());
    }

    public function test_list()
    {
        $result = $this->Tags->list();
        $result = $this->decodeResponse($result);

        $this->assertFalse($result->error);
        $this->assertIsArray($result->data);
    }

    public function test_create_update_delete()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $Tags = $this->Tags;
        $result1 = $Tags->create('ХИТ', 'FEE0E0');
        $result1 = $this->decodeResponse($result1);

        $this->assertFalse($result1->error);

        if(!$result1->error) {
            $this->assertTrue(property_exists($result1, 'data'));
            $id = $result1->data;
            $this->assertIsInt($id);

        $result2 = $Tags->create('ХИТ', 'FEE0E0');
            $result2 = $this->decodeResponse($result2);
            $this->assertTrue($result2->error);
            $this->assertEquals('tag already exists', $result2->errorText);

        $result3 = $Tags->update($id, 'МЕГАХИТ', 'FFECC7');
            $result3 = $this->decodeResponse($result3);
            $this->assertFalse($result3->error);
            
        $result4 = $Tags->delete($id);
            $result4 = $this->decodeResponse($result4);
            $this->assertFalse($result4->error);
        }
    }

    public function test_delete()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Tags->delete(12345);
        $result = $this->decodeResponse($result);
        $this->assertTrue($result->error);
    }

}
