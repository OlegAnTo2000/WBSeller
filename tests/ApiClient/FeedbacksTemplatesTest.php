<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Subpoint\Templates;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class FeedbacksTemplatesTest extends TestCase
{
    private $Templates;
    
    public function setUp(): void
    {
        parent::setUp();
        $this->Templates = $this->FeedbacksTemplates();
    }

    public function test_Class()
    {
        $this->assertInstanceOf(Templates::class, $this->Feedbacks()->Templates());
    }

    public function test_list()
    {
        $result = $this->Templates->list();
        $result = $this->decodeResponse($result);

        $this->assertIsArray($result->data->templates);

        if($result->data->templates) {
            $template = array_shift($result->data->templates);
            
            $this->assertTrue(property_exists($template, 'id'));
            $this->assertTrue(property_exists($template, 'name'));
            $this->assertTrue(property_exists($template, 'text'));
        }
    }

    public function test_crud()
    {
        $this->markTestSkipped('Временно отключено: запрос изменяет данные');
        $result = $this->Templates->create('XYZ-Template', 'Template');
        $result = $this->decodeResponse($result);
        
        $this->assertTrue(property_exists($result, 'data'));
        
        if($result->error == false) {
            $this->assertTrue(property_exists($result->data, 'id'));
            $id = $result->data->id;
            
        $update = $this->Templates->update($id, 'ABC', 'New template');
            $update = $this->decodeResponse($update);
            $this->assertTrue($update->error == false);

        $delete = $this->Templates->delete($id);
            $delete = $this->decodeResponse($delete);
            $this->assertTrue($delete->error == false);
        }
    }

}
