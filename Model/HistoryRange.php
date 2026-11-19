<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Model;

use DateTimeImmutable;
use Magento\Framework\Exception\InputException;

/**
 * Whitelisted chart ranges and how each maps onto a Frankfurter time-series query.
 *
 * Longer ranges are down-sampled by the API (group=week|month) so the chart stays light.
 */
class HistoryRange
{
    public const DEFAULT_RANGE = '3M';

    /**
     * Range code => [relative start modifier, API "group" value or null for daily].
     */
    private const RANGES = [
        '1M' => ['-1 month', null],
        '3M' => ['-3 months', null],
        '6M' => ['-6 months', null],
        '1Y' => ['-1 year', 'week'],
        '5Y' => ['-5 years', 'month'],
    ];

    /**
     * All range codes in display order.
     *
     * @return string[]
     */
    public function getCodes(): array
    {
        return array_keys(self::RANGES);
    }

    /**
     * Whether the range code is supported.
     *
     * @param string $range
     *
     * @return bool
     */
    public function isValid(string $range): bool
    {
        return isset(self::RANGES[$range]);
    }

    /**
     * Start date of the range, relative to $today.
     *
     * @param string $range
     * @param DateTimeImmutable $today
     *
     * @return DateTimeImmutable
     * @throws InputException
     */
    public function getStartDate(string $range, DateTimeImmutable $today): DateTimeImmutable
    {
        $this->assertValid($range);

        return $today->modify(self::RANGES[$range][0]);
    }

    /**
     * API grouping for the range ("week", "month" or null for daily points).
     *
     * @param string $range
     *
     * @return string|null
     * @throws InputException
     */
    public function getGroup(string $range): ?string
    {
        $this->assertValid($range);

        return self::RANGES[$range][1];
    }

    /**
     * Throw if the range is not supported.
     *
     * @param string $range
     *
     * @return void
     * @throws InputException
     */
    private function assertValid(string $range): void
    {
        if (!$this->isValid($range)) {
            throw new InputException(__('Unsupported history range "%1".', $range));
        }
    }
}
