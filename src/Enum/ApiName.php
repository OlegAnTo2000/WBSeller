<?php

declare(strict_types=1);

namespace Dakword\WBSeller\Enum;

enum ApiName: string
{
    case ADV = 'adv';
    case ANALYTICS = 'analytics';
    case CALENDAR = 'calendar';
    case CHAT = 'chat';
    case COMMON = 'common';
    case CONTENT = 'content';
    case DOCUMENTS = 'documents';
    case FEEDBACKS = 'feedbacks';
    case FINANCES = 'finances';
    case MARKETPLACE = 'marketplace';
    case PRICES = 'prices';
    case QUESTIONS = 'questions';
    case RECOMMENDS = 'recommends';
    case RETURNS = 'returns';
    case STATISTICS = 'statistics';
    case SUPPLIES = 'supplies';
    case TARIFFS = 'tariffs';
    case USERS = 'users';
}
