<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Test\Unit\Controller;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Tony\CurrencyConverter\Api\ExchangeRateServiceInterface;
use Tony\CurrencyConverter\Controller\Rate\Index as RateController;
use Tony\CurrencyConverter\Exception\ProviderUnavailableException;
use Tony\CurrencyConverter\Model\Config;
use Tony\CurrencyConverter\Model\Data\Rate;

class RateControllerTest extends TestCase
{
    /**
     * Mocked ExchangeRateServiceInterface.
     *
     * @var ExchangeRateServiceInterface|MockObject
     */
    private ExchangeRateServiceInterface|MockObject $service;
    /**
     * Mocked JsonResult.
     *
     * @var JsonResult|MockObject
     */
    private JsonResult|MockObject $result;
    /**
     * Mocked Config.
     *
     * @var Config|MockObject
     */
    private Config|MockObject $config;
    /**
     * HTTP status code set on the JSON result, if any.
     *
     * @var int|null
     */
    private ?int $code = null;
    /**
     * Payload passed to the JSON result.
     *
     * @var array
     */
    private array $data = [];

    protected function setUp(): void
    {
        $this->service = $this->createMock(ExchangeRateServiceInterface::class);
        $this->config = $this->createMock(Config::class);
        $this->result = $this->createMock(JsonResult::class);
        $this->result->method('setHttpResponseCode')->willReturnCallback(function ($code) {
            $this->code = $code;
            return $this->result;
        });
        $this->result->method('setData')->willReturnCallback(function ($data) {
            $this->data = $data;
            return $this->result;
        });
    }

    private function execute(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['from', '', 'USD'], ['to', '', 'EUR']]);
        $factory = $this->createMock(JsonFactory::class);
        $factory->method('create')->willReturn($this->result);

        (new RateController(
            $request,
            $factory,
            $this->service,
            $this->config,
            $this->createMock(LoggerInterface::class)
        ))->execute();
    }

    public function testSuccess(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->service->method('getRate')->with('USD', 'EUR')
            ->willReturn(new Rate('USD', 'EUR', 0.91, '2026-11-19'));

        $this->execute();

        $this->assertNull($this->code);
        $this->assertSame(['from' => 'USD', 'to' => 'EUR', 'rate' => 0.91, 'date' => '2026-11-19'], $this->data);
    }

    /**
     * Each exception type maps to the expected HTTP status.
     *
     * @dataProvider errorProvider
     */
    public function testErrorMapping(\Throwable $exception, int $expectedCode): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->service->method('getRate')->willThrowException($exception);

        $this->execute();

        $this->assertSame($expectedCode, $this->code);
        $this->assertArrayHasKey('error', $this->data);
    }

    public function errorProvider(): array
    {
        return [
            'bad input' => [new InputException(__('bad')), 400],
            'provider down' => [new ProviderUnavailableException(__('down')), 502],
            'unexpected' => [new RuntimeException('boom'), 500],
        ];
    }

    public function testDisabledReturns404(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->service->expects($this->never())->method('getRate');

        $this->execute();

        $this->assertSame(404, $this->code);
    }
}
