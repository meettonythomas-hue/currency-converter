<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Model\Client;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;
use Throwable;
use Tony\CurrencyConverter\Exception\ProviderUnavailableException;
use Tony\CurrencyConverter\Model\Config;

/**
 * Thin HTTP client for the Frankfurter v2 API (https://frankfurter.dev).
 *
 * Returns decoded JSON arrays; all transport/HTTP/format failures surface as ProviderUnavailableException.
 */
class FrankfurterClient
{
    /**
     * Inject dependencies.
     *
     * @param CurlFactory $curlFactory
     * @param Json $json
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CurlFactory $curlFactory,
        private readonly Json $json,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * GET /currencies.
     *
     * @return array<int, array<string, mixed>>
     * @throws ProviderUnavailableException
     */
    public function fetchCurrencies(): array
    {
        return $this->request('/currencies');
    }

    /**
     * GET /rate/{base}/{quote}.
     *
     * @param string $from
     * @param string $to
     *
     * @return array<string, mixed>
     * @throws ProviderUnavailableException
     */
    public function fetchRate(string $from, string $to): array
    {
        $data = $this->request(sprintf('/rate/%s/%s', rawurlencode($from), rawurlencode($to)));
        if (!isset($data['rate'], $data['date'])) {
            throw $this->unexpectedResponse('rate');
        }

        return $data;
    }

    /**
     * GET /rates?base=..&quotes=..&from=..&to=..[&group=..].
     *
     * @param string $from
     * @param string $to
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param string|null $group week|month|null
     *
     * @return array<int, array<string, mixed>>
     * @throws ProviderUnavailableException
     */
    public function fetchSeries(string $from, string $to, string $startDate, string $endDate, ?string $group): array
    {
        $query = [
            'base' => $from,
            'quotes' => $to,
            'from' => $startDate,
            'to' => $endDate,
        ];
        if ($group !== null) {
            $query['group'] = $group;
        }

        return $this->request('/rates?' . http_build_query($query));
    }

    /**
     * Perform a GET request and decode the JSON body.
     *
     * @param string $pathAndQuery
     *
     * @return array
     * @throws ProviderUnavailableException
     */
    private function request(string $pathAndQuery): array
    {
        $url = $this->config->getBaseUrl() . $pathAndQuery;
        $curl = $this->curlFactory->create();
        $curl->setTimeout($this->config->getTimeout());
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, min(5, $this->config->getTimeout()));
        $curl->addHeader('Accept', 'application/json');
        $curl->addHeader('User-Agent', 'Magento-CurrencyConverter/1.0');

        try {
            $curl->get($url);
        } catch (Throwable $e) {
            $this->logger->error('Currency converter: request failed', ['url' => $url, 'error' => $e->getMessage()]);
            throw new ProviderUnavailableException(__('Exchange rates are temporarily unavailable.'), $e);
        }

        $status = $curl->getStatus();
        $body = (string)$curl->getBody();

        try {
            $data = $body === '' ? null : $this->json->unserialize($body);
        } catch (Throwable $e) {
            $data = null;
        }

        if ($status !== 200 || !is_array($data)) {
            $this->logger->error('Currency converter: unexpected API response', [
                'url' => $url,
                'status' => $status,
                'message' => is_array($data) ? ($data['message'] ?? null) : substr($body, 0, 200),
            ]);
            throw new ProviderUnavailableException(__('Exchange rates are temporarily unavailable.'));
        }

        return $data;
    }

    /**
     * Build an exception for a response with an unexpected shape.
     *
     * @param string $what
     *
     * @return ProviderUnavailableException
     */
    private function unexpectedResponse(string $what): ProviderUnavailableException
    {
        $this->logger->error('Currency converter: malformed API response', ['endpoint' => $what]);

        return new ProviderUnavailableException(__('Exchange rates are temporarily unavailable.'));
    }
}
