<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Test\Unit\Model;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tony\CurrencyConverter\Api\Data\CurrencyInterfaceFactory;
use Tony\CurrencyConverter\Api\Data\HistoryPointInterfaceFactory;
use Tony\CurrencyConverter\Api\Data\RateInterfaceFactory;
use Tony\CurrencyConverter\Model\Cache\Type as CacheType;
use Tony\CurrencyConverter\Model\Client\FrankfurterClient;
use Tony\CurrencyConverter\Model\Config;
use Tony\CurrencyConverter\Model\Data\Currency;
use Tony\CurrencyConverter\Model\Data\HistoryPoint;
use Tony\CurrencyConverter\Model\Data\Rate;
use Tony\CurrencyConverter\Model\ExchangeRateService;
use Tony\CurrencyConverter\Model\HistoryRange;

class ExchangeRateServiceTest extends TestCase
{
    private const CURRENCIES = [
        ['iso_code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'type' => 'currency'],
        ['iso_code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'type' => 'currency'],
        ['iso_code' => 'BTC', 'name' => 'Bitcoin', 'symbol' => '₿', 'type' => 'crypto'],
        ['iso_code' => 'XAU', 'name' => 'Gold', 'symbol' => 'XAU', 'type' => 'metal'],
        ['iso_code' => '<bad>', 'name' => 'Broken'],
    ];

    /**
     * Mocked FrankfurterClient.
     *
     * @var FrankfurterClient|MockObject
     */
    private FrankfurterClient|MockObject $client;
    /**
     * Mocked CacheType.
     *
     * @var CacheType|MockObject
     */
    private CacheType|MockObject $cache;
    /**
     * Mocked StateInterface.
     *
     * @var StateInterface|MockObject
     */
    private StateInterface|MockObject $cacheState;
    /**
     * Mocked Config.
     *
     * @var Config|MockObject
     */
    private Config|MockObject $config;

    /**
     * In-memory cache backend used by the cache mock (key => serialized data).
     *
     * @var array<string, string>
     */
    private array $store = [];

    protected function setUp(): void
    {
        $this->client = $this->createMock(FrankfurterClient::class);
        $this->cache = $this->createMock(CacheType::class);
        $this->cacheState = $this->createMock(StateInterface::class);
        $this->config = $this->createMock(Config::class);

        $this->cache->method('load')->willReturnCallback(fn ($key) => $this->store[$key] ?? false);
        $this->cache->method('save')->willReturnCallback(function ($data, $key) {
            $this->store[$key] = $data;
            return true;
        });
        $this->cacheState->method('isEnabled')->willReturn(true);

        $this->config->method('getBaseUrl')->willReturn('https://api.example.test/v2');
        $this->config->method('getCurrenciesTtl')->willReturn(86400);
        $this->config->method('getRateTtl')->willReturn(3600);
        $this->config->method('getHistoryTtl')->willReturn(3600);
    }

    private function createService(bool $includeCrypto = true): ExchangeRateService
    {
        $this->config->method('isCryptoIncluded')->willReturn($includeCrypto);

        $currencyFactory = $this->createMock(CurrencyInterfaceFactory::class);
        $currencyFactory->method('create')->willReturnCallback(fn (array $a) => new Currency(...$a));
        $rateFactory = $this->createMock(RateInterfaceFactory::class);
        $rateFactory->method('create')->willReturnCallback(fn (array $a) => new Rate(...$a));
        $pointFactory = $this->createMock(HistoryPointInterfaceFactory::class);
        $pointFactory->method('create')->willReturnCallback(fn (array $a) => new HistoryPoint(...$a));

        return new ExchangeRateService(
            $this->client,
            $this->cache,
            $this->cacheState,
            new Json(),
            $this->config,
            new HistoryRange(),
            $currencyFactory,
            $rateFactory,
            $pointFactory
        );
    }

    public function testCurrenciesAreSortedAndInvalidCodesDropped(): void
    {
        $this->client->method('fetchCurrencies')->willReturn(self::CURRENCIES);

        $codes = array_map(fn ($c) => $c->getCode(), $this->createService()->getCurrencies());

        $this->assertSame(['BTC', 'EUR', 'USD', 'XAU'], $codes);
    }

    public function testCryptoCanBeExcluded(): void
    {
        $this->client->method('fetchCurrencies')->willReturn(self::CURRENCIES);

        $codes = array_map(fn ($c) => $c->getCode(), $this->createService(false)->getCurrencies());

        $this->assertSame(['EUR', 'USD', 'XAU'], $codes);
    }

    public function testGetRateNormalisesInputAndUsesCache(): void
    {
        $this->client->expects($this->once())->method('fetchCurrencies')->willReturn(self::CURRENCIES);
        $this->client->expects($this->once())->method('fetchRate')->with('USD', 'EUR')
            ->willReturn(['date' => '2026-11-19', 'base' => 'USD', 'quote' => 'EUR', 'rate' => 0.91]);

        $first = $this->createService()->getRate(' usd ', 'eur');
        // New service instance: must be served from cache (no second API call).
        $second = $this->createService()->getRate('USD', 'EUR');

        $this->assertSame('USD', $first->getFrom());
        $this->assertSame('EUR', $first->getTo());
        $this->assertSame(0.91, $first->getRate());
        $this->assertSame('2026-11-19', $first->getDate());
        $this->assertSame(0.91, $second->getRate());
    }

    public function testCacheIsBypassedWhenCacheTypeDisabled(): void
    {
        $this->cacheState = $this->createMock(StateInterface::class);
        $this->cacheState->method('isEnabled')->willReturn(false);
        $this->client->expects($this->exactly(2))->method('fetchCurrencies')->willReturn(self::CURRENCIES);

        $this->createService()->getCurrencies();
        $this->createService()->getCurrencies();

        $this->assertSame([], $this->store);
    }

    public function testUnknownCurrencyIsRejectedWithoutCallingRateApi(): void
    {
        $this->client->method('fetchCurrencies')->willReturn(self::CURRENCIES);
        $this->client->expects($this->never())->method('fetchRate');

        $this->expectException(InputException::class);
        $this->createService()->getRate('USD', 'XXX');
    }

    public function testCryptoIsRejectedWhenExcluded(): void
    {
        $this->client->method('fetchCurrencies')->willReturn(self::CURRENCIES);

        $this->expectException(InputException::class);
        $this->createService(false)->getRate('USD', 'BTC');
    }

    public function testHistoryIsSortedAndSkipsMalformedRows(): void
    {
        $this->client->method('fetchCurrencies')->willReturn(self::CURRENCIES);
        $this->client->expects($this->once())->method('fetchSeries')
            ->with('USD', 'EUR', $this->isType('string'), $this->isType('string'), 'month')
            ->willReturn([
                ['date' => '2026-02-01', 'rate' => 0.93],
                ['date' => '2026-01-01', 'rate' => 0.92],
                ['date' => '2026-03-01'],
            ]);

        $points = $this->createService()->getHistory('USD', 'EUR', '5y');

        $this->assertCount(2, $points);
        $this->assertSame('2026-01-01', $points[0]->getDate());
        $this->assertSame(0.93, $points[1]->getRate());
    }

    public function testHistoryRejectsUnknownRange(): void
    {
        $this->client->method('fetchCurrencies')->willReturn(self::CURRENCIES);
        $this->client->expects($this->never())->method('fetchSeries');

        $this->expectException(InputException::class);
        $this->createService()->getHistory('USD', 'EUR', '10Y');
    }
}
