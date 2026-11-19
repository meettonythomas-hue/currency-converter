<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * Dedicated cache type so rates can be flushed independently from System > Cache Management.
 */
class Type extends TagScope
{
    public const TYPE_IDENTIFIER = 'currency_converter';
    public const CACHE_TAG = 'CURRENCY_CONVERTER';

    /**
     * Inject dependencies.
     *
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
