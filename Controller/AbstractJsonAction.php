<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Controller;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\InputException;
use Psr\Log\LoggerInterface;
use Throwable;
use Tony\CurrencyConverter\Api\ExchangeRateServiceInterface;
use Tony\CurrencyConverter\Exception\ProviderUnavailableException;
use Tony\CurrencyConverter\Model\Config;

/**
 * Shared JSON response / error mapping for the converter AJAX endpoints.
 *
 * 400 = bad input, 404 = feature disabled, 502 = provider unavailable, 500 = anything else.
 */
abstract class AbstractJsonAction implements HttpGetActionInterface
{
    /**
     * Inject dependencies.
     *
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param ExchangeRateServiceInterface $exchangeRateService
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        protected readonly ExchangeRateServiceInterface $exchangeRateService,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Build the success payload.
     *
     * @return array
     * @throws InputException
     * @throws ProviderUnavailableException
     */
    abstract protected function getPayload(): array;

    /**
     * Execute the action.
     *
     * @return JsonResult
     */
    public function execute(): JsonResult
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setHttpResponseCode(404)->setData(['error' => (string)__('Not found.')]);
        }

        try {
            return $result->setData($this->getPayload());
        } catch (InputException $e) {
            return $result->setHttpResponseCode(400)->setData(['error' => $e->getMessage()]);
        } catch (ProviderUnavailableException $e) {
            return $result->setHttpResponseCode(502)->setData(['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->logger->critical($e);

            return $result->setHttpResponseCode(500)
                ->setData(['error' => (string)__('Something went wrong. Please try again.')]);
        }
    }

    /**
     * Read a string request parameter.
     *
     * @param string $name
     *
     * @return string
     */
    protected function getStringParam(string $name): string
    {
        $value = $this->request->getParam($name, '');

        return is_string($value) ? $value : '';
    }
}
