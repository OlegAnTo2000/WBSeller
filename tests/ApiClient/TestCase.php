<?php

namespace Dakword\WBSeller\Tests\ApiClient;

use Dakword\WBSeller\API\Endpoint\Adv;
use Dakword\WBSeller\API\Endpoint\Analytics;
use Dakword\WBSeller\API\Endpoint\Calendar;
use Dakword\WBSeller\API\Endpoint\Common;
use Dakword\WBSeller\API\Endpoint\Content;
use Dakword\WBSeller\API\Endpoint\Feedbacks;
use Dakword\WBSeller\API\Endpoint\Marketplace;
use Dakword\WBSeller\API\Endpoint\Prices;
use Dakword\WBSeller\API\Endpoint\Questions;
use Dakword\WBSeller\API\Endpoint\Recommends;
use Dakword\WBSeller\API\Endpoint\Statistics;
use Dakword\WBSeller\API\Endpoint\Supplies;
use Dakword\WBSeller\API\Endpoint\Tariffs;
use Dakword\WBSeller\API\Endpoint\Subpoint\DBS;
use Dakword\WBSeller\API\Endpoint\Subpoint\News;
use Dakword\WBSeller\API\Endpoint\Subpoint\Passes;
use Dakword\WBSeller\API\Endpoint\Subpoint\Tags;
use Dakword\WBSeller\API\Endpoint\Subpoint\Templates;
use Dakword\WBSeller\API\Endpoint\Subpoint\Warehouses;
use Dakword\WBSeller\API\Response\ApiResponse;
use Dakword\WBSeller\Exception\ApiTimeRestrictionsException;
use Dakword\WBSeller\Enum\ApiName;
use Dakword\WBSeller\Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function decodeResponse(mixed $value): mixed
    {
        return $value instanceof ApiResponse ? $value->json() : $value;
    }

    protected function Adv(): Adv
    {
        $this->skipIfNoPromotionKeyAPI();
        return $this->PromotionAPI()->Adv();
    }

    protected function Analytics(): Analytics
    {
        $this->skipIfNoKeyAPI(ApiName::ANALYTICS);
        return $this->API()->Analytics();
    }

    protected function Calendar(): Calendar
    {
        $this->skipIfNoKeyAPI(ApiName::CALENDAR);
        return $this->API()->Calendar();
    }

    protected function Content(): Content
    {
        $this->skipIfNoKeyAPI(ApiName::CONTENT);
        return $this->API()->Content();
    }

    protected function Common(): Common
    {
        $this->skipIfNoKeyAPI(ApiName::COMMON);
        return $this->API()->Common();
    }

    protected function CommonNews(): News
    {
        $this->skipIfNoKeyAPI(ApiName::COMMON);
        return $this->API()->Common()->News();
    }

    protected function ContentTags(): Tags
    {
        $this->skipIfNoKeyAPI(ApiName::CONTENT);
        return $this->API()->Content()->Tags();
    }

    protected function Feedbacks(): Feedbacks
    {
        $this->skipIfNoKeyAPI(ApiName::FEEDBACKS);
        return $this->API()->Feedbacks();
    }

    protected function FeedbacksTemplates(): Templates
    {
        $this->skipIfNoKeyAPI(ApiName::FEEDBACKS);
        return $this->API()->Feedbacks()->Templates();
    }

    protected function Marketplace(): Marketplace
    {
        $this->skipIfNoKeyAPI(ApiName::MARKETPLACE);
        return $this->API()->Marketplace();
    }

    protected function MarketplaceDBS(): DBS
    {
        $this->skipIfNoKeyAPI(ApiName::MARKETPLACE);
        return $this->API()->Marketplace()->DBS();
    }

    protected function MarketplacePasses(): Passes
    {
        $this->skipIfNoKeyAPI(ApiName::MARKETPLACE);
        return $this->API()->Marketplace()->Passes();
    }

    protected function MarketplaceWarehouses(): Warehouses
    {
        $this->skipIfNoKeyAPI(ApiName::MARKETPLACE);
        return $this->API()->Marketplace()->Warehouses();
    }

    protected function Prices(): Prices
    {
        $this->skipIfNoKeyAPI(ApiName::PRICES);
        return $this->API()->Prices();
    }

    protected function Questions(): Questions
    {
        $this->skipIfNoKeyAPI(ApiName::QUESTIONS);
        return $this->API()->Questions();
    }

    protected function QuestionsTemplates(): Templates
    {
        $this->skipIfNoKeyAPI(ApiName::QUESTIONS);
        return $this->API()->Questions()->Templates();
    }

    protected function Recommends(): Recommends
    {
        $this->skipIfNoKeyAPI(ApiName::RECOMMENDS);
        return $this->API()->Recommends();
    }

    protected function Statistics(): Statistics
    {
        $this->skipIfNoKeyAPI(ApiName::STATISTICS);
        return $this->API()->Statistics();
    }

    protected function Supplies(): Supplies
    {
        $this->skipIfNoKeyAPI(ApiName::SUPPLIES);
        return $this->API()->Supplies();
    }

    protected function Tariffs(): Tariffs
    {
        $this->skipIfNoKeyAPI(ApiName::TARIFFS);
        return $this->API()->Tariffs();
    }

    protected function getRealNms($limit = 10)
    {
        try {
            $result = $this->Content()->getCardsList('', $limit)->json();
        } catch (ApiTimeRestrictionsException $exc) {
            $this->markTestSkipped($exc->getMessage());
        }

        if (count($result->cards) == 0) {
            $this->markTestSkipped('No cards in account');
        }

        return array_column($result->cards, 'nmID');
    }

}
