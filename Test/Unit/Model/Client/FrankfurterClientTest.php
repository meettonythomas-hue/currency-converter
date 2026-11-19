<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Test\Unit\Model\Client;

use Exception;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tony\CurrencyConverter\Exception\ProviderUnavailableException;
use Tony\CurrencyConverter\Model\Client\FrankfurterClient;
use Tony\CurrencyConverter\Model\Config;

class FrankfurterClientTest extends TestCase
{
    /**
     * Mocked Curl.
     *
     * @var Curl|MockObject
     */
    private Curl|MockObject $curl;
    /**
     * Subject under test.
     *
     * @var FrankfurterClient
     */
    private FrankfurterClient $client;

    protected function setUp(): void
    {
        $this->curl = $this->createMock(Curl::class);
        $curlFactory = $this->createMock(CurlFactory::class);
        $curlFactory->method('create')->willReturn($this->curl);

        $config = $this->createMock(Config::class);
        $config->method('getBaseUrl')->willReturn('https://api.example.test/v2');
        $config->method('getTimeout')->willReturn(10);

        $this->client = new FrankfurterClient(
            $curlFactory,
            new Json(),
            $config,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testFetchRateBuildsUrlAndDecodes(): void
    {
        $this->curl->expects($this->once())->method('get')
            ->with('https://api.example.test/v2/rate/USD/INR');
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')
            ->willReturn('{"date":"2026-11-19","base":"USD","quote":"INR","rate":93.32}');

        $data = $this->client->fetchRate('USD', 'INR');

        $this->assertSame(93.32, $data['rate']);
        $this->assertSame('2026-11-19', $data['date']);
    }

    public function testFetchSeriesPassesGroupOnlyWhenSet(): void
    {
        $this->curl->expects($this->exactly(2))->method('get')
            ->withConsecutive(
                ['https://api.example.test/v2/rates?base=USD&quotes=EUR&from=2026-10-19&to=2026-11-19'],
                ['https://api.example.test/v2/rates?base=USD&quotes=EUR&from=2021-11-19&to=2026-11-19&group=month']
            );
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('[]');

        $this->assertSame([], $this->client->fetchSeries('USD', 'EUR', '2026-10-19', '2026-11-19', null));
        $this->assertSame([], $this->client->fetchSeries('USD', 'EUR', '2021-11-19', '2026-11-19', 'month'));
    }

    public function testHttpErrorBecomesProviderUnavailable(): void
    {
        $this->curl->method('getStatus')->willReturn(422);
        $this->curl->method('getBody')->willReturn('{"status":422,"message":"invalid currency: XXX"}');

        $this->expectException(ProviderUnavailableException::class);
        $this->client->fetchRate('USD', 'XXX');
    }

    public function testTransportErrorBecomesProviderUnavailable(): void
    {
        $this->curl->method('get')->willThrowException(new Exception('Connection timed out'));

        $this->expectException(ProviderUnavailableException::class);
        $this->client->fetchCurrencies();
    }

    public function testMalformedJsonBecomesProviderUnavailable(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('<html>oops</html>');

        $this->expectException(ProviderUnavailableException::class);
        $this->client->fetchCurrencies();
    }

    public function testRateResponseWithoutRateIsRejected(): void
    {
        $this->curl->method('getStatus')->willReturn(200);
        $this->curl->method('getBody')->willReturn('{"date":"2026-11-19"}');

        $this->expectException(ProviderUnavailableException::class);
        $this->client->fetchRate('USD', 'EUR');
    }
}
