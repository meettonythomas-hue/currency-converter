<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Api\Data;

/**
 * Currency supported by the exchange-rate provider.
 *
 * @api
 */
interface CurrencyInterface
{
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_CRYPTO = 'crypto';
    public const TYPE_METAL = 'metal';

    /**
     * ISO (or provider) code, e.g. "USD", "BTC".
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Human readable name, e.g. "US Dollar".
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Display symbol, e.g. "$".
     *
     * @return string
     */
    public function getSymbol(): string;

    /**
     * One of the TYPE_* constants.
     *
     * @return string
     */
    public function getType(): string;
}
