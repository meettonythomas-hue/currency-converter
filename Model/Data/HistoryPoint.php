<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Model\Data;

use Tony\CurrencyConverter\Api\Data\HistoryPointInterface;

/**
 * Immutable time-series point DTO.
 */
class HistoryPoint implements HistoryPointInterface
{
    /**
     * Create an immutable value object.
     *
     * @param string $date
     * @param float $rate
     */
    public function __construct(
        private readonly string $date,
        private readonly float $rate
    ) {
    }

    /**
     * Date of this point.
     *
     * @return string
     */
    public function getDate(): string
    {
        return $this->date;
    }

    /**
     * Rate on this date.
     *
     * @return float
     */
    public function getRate(): float
    {
        return $this->rate;
    }
}
