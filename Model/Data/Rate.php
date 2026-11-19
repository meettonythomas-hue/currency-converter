<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Model\Data;

use Tony\CurrencyConverter\Api\Data\RateInterface;

/**
 * Immutable rate DTO.
 */
class Rate implements RateInterface
{
    /**
     * Create an immutable value object.
     *
     * @param string $from
     * @param string $to
     * @param float $rate
     * @param string $date
     */
    public function __construct(
        private readonly string $from,
        private readonly string $to,
        private readonly float $rate,
        private readonly string $date
    ) {
    }

    /**
     * Base currency code.
     *
     * @return string
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Quote currency code.
     *
     * @return string
     */
    public function getTo(): string
    {
        return $this->to;
    }

    /**
     * Units of the quote currency per one unit of the base currency.
     *
     * @return float
     */
    public function getRate(): float
    {
        return $this->rate;
    }

    /**
     * Date the rate was published.
     *
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }
}
