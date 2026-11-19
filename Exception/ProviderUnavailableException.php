<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * The exchange-rate provider could not be reached or returned an unusable response.
 */
class ProviderUnavailableException extends LocalizedException
{
}
