<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to Stores > Configuration > General > Currency Converter.
 */
class Config
{
    public const XML_PATH_ENABLED = 'currency_converter/general/enabled';
    public const XML_PATH_DEFAULT_FROM = 'currency_converter/general/default_from';
    public const XML_PATH_DEFAULT_TO = 'currency_converter/general/default_to';
    public const XML_PATH_INCLUDE_CRYPTO = 'currency_converter/general/include_crypto';
    public const XML_PATH_SHOW_FOOTER_LINK = 'currency_converter/general/show_footer_link';
    public const XML_PATH_BASE_URL = 'currency_converter/api/base_url';
    public const XML_PATH_TIMEOUT = 'currency_converter/api/timeout';
    public const XML_PATH_CURRENCIES_TTL = 'currency_converter/api/currencies_ttl';
    public const XML_PATH_RATE_TTL = 'currency_converter/api/rate_ttl';
    public const XML_PATH_HISTORY_TTL = 'currency_converter/api/history_ttl';

    private const DEFAULT_BASE_URL = 'https://api.frankfurter.dev/v2';

    /**
     * Inject dependencies.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Whether the storefront page is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Default "from" currency code.
     *
     * @return string
     */
    public function getDefaultFrom(): string
    {
        return strtoupper(trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_FROM,
            ScopeInterface::SCOPE_STORE
        )));
    }

    /**
     * Default "to" currency code.
     *
     * @return string
     */
    public function getDefaultTo(): string
    {
        return strtoupper(trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_TO,
            ScopeInterface::SCOPE_STORE
        )));
    }

    /**
     * Whether cryptocurrencies are offered.
     *
     * @return bool
     */
    public function isCryptoIncluded(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_INCLUDE_CRYPTO, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Whether the footer link is shown.
     *
     * @return bool
     */
    public function isFooterLinkShown(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SHOW_FOOTER_LINK, ScopeInterface::SCOPE_STORE);
    }

    /**
     * API base URL without trailing slash.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        $url = trim((string)$this->scopeConfig->getValue(self::XML_PATH_BASE_URL));

        return rtrim($url !== '' ? $url : self::DEFAULT_BASE_URL, '/');
    }

    /**
     * HTTP timeout in seconds.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_TIMEOUT));
    }

    /**
     * Cache lifetime for the currency list.
     *
     * @return int
     */
    public function getCurrenciesTtl(): int
    {
        return max(0, (int)$this->scopeConfig->getValue(self::XML_PATH_CURRENCIES_TTL));
    }

    /**
     * Cache lifetime for current rates.
     *
     * @return int
     */
    public function getRateTtl(): int
    {
        return max(0, (int)$this->scopeConfig->getValue(self::XML_PATH_RATE_TTL));
    }

    /**
     * Cache lifetime for history series.
     *
     * @return int
     */
    public function getHistoryTtl(): int
    {
        return max(0, (int)$this->scopeConfig->getValue(self::XML_PATH_HISTORY_TTL));
    }
}
