<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Model;

use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Serialize\Serializer\Json;
use Tony\CurrencyConverter\Api\Data\CurrencyInterface;
use Tony\CurrencyConverter\Api\Data\CurrencyInterfaceFactory;
use Tony\CurrencyConverter\Api\Data\HistoryPointInterface;
use Tony\CurrencyConverter\Api\Data\HistoryPointInterfaceFactory;
use Tony\CurrencyConverter\Api\Data\RateInterface;
use Tony\CurrencyConverter\Api\Data\RateInterfaceFactory;
use Tony\CurrencyConverter\Api\ExchangeRateServiceInterface;
use Tony\CurrencyConverter\Exception\ProviderUnavailableException;
use Tony\CurrencyConverter\Model\Cache\Type as CacheType;
use Tony\CurrencyConverter\Model\Client\FrankfurterClient;

/**
 * Validates input, caches provider responses and maps them onto DTOs.
 */
class ExchangeRateService implements ExchangeRateServiceInterface
{
    private const CACHE_PREFIX = 'TONY_CC_';

    /**
     * Request-level memo of the filtered currency list, keyed by code.
     *
     * @var array<string, CurrencyInterface>|null
     */
    private ?array $currencies = null;

    /**
     * Inject dependencies.
     *
     * @param FrankfurterClient $client
     * @param CacheType $cache
     * @param CacheState $cacheState
     * @param Json $json
     * @param Config $config
     * @param HistoryRange $historyRange
     * @param CurrencyInterfaceFactory $currencyFactory
     * @param RateInterfaceFactory $rateFactory
     * @param HistoryPointInterfaceFactory $historyPointFactory
     */
    public function __construct(
        private readonly FrankfurterClient $client,
        private readonly CacheType $cache,
        private readonly CacheState $cacheState,
        private readonly Json $json,
        private readonly Config $config,
        private readonly HistoryRange $historyRange,
        private readonly CurrencyInterfaceFactory $currencyFactory,
        private readonly RateInterfaceFactory $rateFactory,
        private readonly HistoryPointInterfaceFactory $historyPointFactory
    ) {
    }

    /**
     * Currencies offered on the storefront, filtered by configuration.
     *
     * @return CurrencyInterface[]
     * @throws ProviderUnavailableException
     */
    public function getCurrencies(): array
    {
        return array_values($this->getCurrencyMap());
    }

    /**
     * Latest rate for the pair, served from cache when possible.
     *
     * @param string $from
     * @param string $to
     *
     * @return RateInterface
     * @throws InputException
     * @throws ProviderUnavailableException
     */
    public function getRate(string $from, string $to): RateInterface
    {
        [$from, $to] = $this->validatePair($from, $to);

        $data = $this->cached(
            'rate_' . $from . '_' . $to,
            $this->config->getRateTtl(),
            fn () => $this->client->fetchRate($from, $to)
        );

        return $this->rateFactory->create([
            'from' => $from,
            'to' => $to,
            'rate' => (float)$data['rate'],
            'date' => (string)$data['date'],
        ]);
    }

    /**
     * Rate history for the pair over a whitelisted range, oldest first.
     *
     * @param string $from
     * @param string $to
     * @param string $range
     *
     * @return HistoryPointInterface[]
     * @throws InputException
     * @throws ProviderUnavailableException
     */
    public function getHistory(string $from, string $to, string $range): array
    {
        [$from, $to] = $this->validatePair($from, $to);
        $range = strtoupper(trim($range));
        if (!$this->historyRange->isValid($range)) {
            throw new InputException(__('Unsupported history range "%1".', $range));
        }

        $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $start = $this->historyRange->getStartDate($range, $today)->format('Y-m-d');
        $end = $today->format('Y-m-d');
        $group = $this->historyRange->getGroup($range);

        $rows = $this->cached(
            sprintf('history_%s_%s_%s_%s', $from, $to, $range, $end),
            $this->config->getHistoryTtl(),
            fn () => $this->client->fetchSeries($from, $to, $start, $end, $group)
        );

        $points = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['date'], $row['rate'])) {
                continue;
            }
            $points[] = $this->historyPointFactory->create([
                'date' => (string)$row['date'],
                'rate' => (float)$row['rate'],
            ]);
        }
        usort($points, static fn ($a, $b) => strcmp($a->getDate(), $b->getDate()));

        return $points;
    }

    /**
     * Normalise and validate a currency pair against the available currency list.
     *
     * @param string $from
     * @param string $to
     *
     * @return string[]
     * @throws InputException
     */
    private function validatePair(string $from, string $to): array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        $map = $this->getCurrencyMap();

        foreach (['from' => $from, 'to' => $to] as $field => $code) {
            if ($code === '' || !isset($map[$code])) {
                throw new InputException(__('Unsupported "%1" currency "%2".', $field, $code));
            }
        }

        return [$from, $to];
    }

    /**
     * Currency list keyed by code, filtered by configuration.
     *
     * @return array<string, CurrencyInterface>
     */
    private function getCurrencyMap(): array
    {
        if ($this->currencies !== null) {
            return $this->currencies;
        }

        $rows = $this->cached(
            'currencies',
            $this->config->getCurrenciesTtl(),
            fn () => $this->client->fetchCurrencies()
        );
        $includeCrypto = $this->config->isCryptoIncluded();

        $map = [];
        foreach ($rows as $row) {
            $code = is_array($row) ? strtoupper((string)($row['iso_code'] ?? '')) : '';
            if ($code === '' || !preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
                continue;
            }
            $type = (string)($row['type'] ?? CurrencyInterface::TYPE_CURRENCY);
            if (!$includeCrypto && $type === CurrencyInterface::TYPE_CRYPTO) {
                continue;
            }
            $map[$code] = $this->currencyFactory->create([
                'code' => $code,
                'name' => (string)($row['name'] ?? $code),
                'symbol' => (string)($row['symbol'] ?? ''),
                'type' => $type,
            ]);
        }
        ksort($map);

        return $this->currencies = $map;
    }

    /**
     * Return cached JSON-decoded data or load and cache it.
     *
     * @param string $key
     * @param int $ttl
     * @param callable $loader
     *
     * @return array
     */
    private function cached(string $key, int $ttl, callable $loader): array
    {
        $enabled = $ttl > 0 && $this->cacheState->isEnabled(CacheType::TYPE_IDENTIFIER);
        $cacheKey = self::CACHE_PREFIX . hash('sha256', $this->config->getBaseUrl() . '|' . $key);

        if ($enabled) {
            $hit = $this->cache->load($cacheKey);
            if (is_string($hit) && $hit !== '') {
                $decoded = $this->json->unserialize($hit);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $data = $loader();
        if ($enabled) {
            $this->cache->save($this->json->serialize($data), $cacheKey, [CacheType::CACHE_TAG], $ttl);
        }

        return $data;
    }
}
