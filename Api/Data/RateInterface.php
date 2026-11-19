<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Api\Data;

/**
 * Exchange rate for one currency pair on one date.
 *
 * @api
 */
interface RateInterface
{
    /**
     * Base currency code.
     *
     * @return string
     */
    public function getFrom(): string;

    /**
     * Quote currency code.
     *
     * @return string
     */
    public function getTo(): string;

    /**
     * Units of "to" for one unit of "from".
     *
     * @return float
     */
    public function getRate(): float;

    /**
     * Date the rate was published (Y-m-d).
     *
     * @return string
     */
    public function getDate(): string;
}
