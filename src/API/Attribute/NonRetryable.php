<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class NonRetryable
{
}
