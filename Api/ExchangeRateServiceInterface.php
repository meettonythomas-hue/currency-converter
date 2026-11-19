<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Api;

use Magento\Framework\Exception\InputException;
use Tony\CurrencyConverter\Api\Data\CurrencyInterface;
use Tony\CurrencyConverter\Api\Data\HistoryPointInterface;
use Tony\CurrencyConverter\Api\Data\RateInterface;
use Tony\CurrencyConverter\Exception\ProviderUnavailableException;

/**
 * Exchange-rate lookups (currency list, current rate, history).
 *
 * @api
 */
interface ExchangeRateServiceInterface
{
    /**
     * Currencies available on the storefront (respects the "include crypto" setting).
     *
     * @return CurrencyInterface[]
     * @throws ProviderUnavailableException
     */
    public function getCurrencies(): array;

    /**
     * Latest published rate for a currency pair.
     *
     * @param string $from
     * @param string $to
     *
     * @return RateInterface
     * @throws InputException
     * @throws ProviderUnavailableException
     */
    public function getRate(string $from, string $to): RateInterface;

    /**
     * Rate history for a currency pair over a predefined range (see Model\HistoryRange).
     *
     * @param string $from
     * @param string $to
     * @param string $range
     *
     * @return HistoryPointInterface[]
     * @throws InputException
     * @throws ProviderUnavailableException
     */
    public function getHistory(string $from, string $to, string $range): array;
}
