<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Test\Unit\Model;

use DateTimeImmutable;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\TestCase;
use Tony\CurrencyConverter\Model\HistoryRange;

class HistoryRangeTest extends TestCase
{
    /**
     * Subject under test.
     *
     * @var HistoryRange
     */
    private HistoryRange $range;

    protected function setUp(): void
    {
        $this->range = new HistoryRange();
    }

    /**
     * Each range maps to the expected start date and API grouping.
     *
     * @dataProvider rangeProvider
     */
    public function testStartDateAndGroup(string $code, string $expectedStart, ?string $expectedGroup): void
    {
        $today = new DateTimeImmutable('2026-11-19');

        $this->assertSame($expectedStart, $this->range->getStartDate($code, $today)->format('Y-m-d'));
        $this->assertSame($expectedGroup, $this->range->getGroup($code));
    }

    public function rangeProvider(): array
    {
        return [
            ['1M', '2026-10-19', null],
            ['3M', '2026-08-19', null],
            ['6M', '2026-05-19', null],
            ['1Y', '2025-11-19', 'week'],
            ['5Y', '2021-11-19', 'month'],
        ];
    }

    public function testCodesIncludeDefault(): void
    {
        $this->assertContains(HistoryRange::DEFAULT_RANGE, $this->range->getCodes());
    }

    public function testInvalidRangeThrows(): void
    {
        $this->assertFalse($this->range->isValid('10Y'));
        $this->expectException(InputException::class);
        $this->range->getGroup('10Y');
    }
}
