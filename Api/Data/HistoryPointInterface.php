<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Api\Data;

/**
 * Single point of a rate time series.
 *
 * @api
 */
interface HistoryPointInterface
{
    /**
     * Date (Y-m-d).
     *
     * @return string
     */
    public function getDate(): string;

    /**
     * Rate on that date.
     *
     * @return float
     */
    public function getRate(): float;
}
