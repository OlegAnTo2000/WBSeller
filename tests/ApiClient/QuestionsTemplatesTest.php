<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Subpoint\Templates;
use Dakword\WBSeller\Tests\ApiClient\TestCase;

class QuestionsTemplatesTest extends TestCase
{
    private $Templates;
    
    public function setUp(): void
    {
        parent::setUp();
        $this->Templates = $this->QuestionsTemplates();
    }

    public function test_Class()
    {
        $this->assertInstanceOf(Templates::class, $this->Questions()->Templates());
    }

    public function test_list()
    {
        $result = $this->Templates->list();

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
        $result = $this->Templates->create('XYZ-Template', 'Template');
        
        $this->assertTrue(property_exists($result, 'data'));
        
        if($result->error == false) {
            $this->assertTrue(property_exists($result->data, 'id'));
            $id = $result->data->id;
            
            $update = $this->Templates->update($id, 'ABC', 'New template');
            $this->assertTrue($update->error == false);

            $delete = $this->Templates->delete($id);
            $this->assertTrue($delete->error == false);
        }
    }

}
