<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Model\Data;

use Tony\CurrencyConverter\Api\Data\CurrencyInterface;

/**
 * Immutable currency DTO.
 */
class Currency implements CurrencyInterface
{
    /**
     * Create an immutable value object.
     *
     * @param string $code
     * @param string $name
     * @param string $symbol
     * @param string $type
     */
    public function __construct(
        private readonly string $code,
        private readonly string $name,
        private readonly string $symbol = '',
        private readonly string $type = self::TYPE_CURRENCY
    ) {
    }

    /**
     * Currency code.
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Human readable currency name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Display symbol.
     *
     * @return string
     */
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * Currency type (currency, metal or crypto).
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
