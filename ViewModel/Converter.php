<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\ViewModel;

use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;
use Tony\CurrencyConverter\Api\Data\CurrencyInterface;
use Tony\CurrencyConverter\Api\ExchangeRateServiceInterface;
use Tony\CurrencyConverter\Exception\ProviderUnavailableException;
use Tony\CurrencyConverter\Model\Config;
use Tony\CurrencyConverter\Model\HistoryRange;

/**
 * Supplies the converter UI component with its initial configuration.
 */
class Converter implements ArgumentInterface
{
    private const GROUP_LABELS = [
        CurrencyInterface::TYPE_CURRENCY => 'Currencies',
        CurrencyInterface::TYPE_METAL => 'Precious metals',
        CurrencyInterface::TYPE_CRYPTO => 'Cryptocurrencies',
    ];

    /**
     * Inject dependencies.
     *
     * @param ExchangeRateServiceInterface $exchangeRateService
     * @param Config $config
     * @param HistoryRange $historyRange
     * @param UrlInterface $urlBuilder
     * @param JsonHexTag $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ExchangeRateServiceInterface $exchangeRateService,
        private readonly Config $config,
        private readonly HistoryRange $historyRange,
        private readonly UrlInterface $urlBuilder,
        private readonly JsonHexTag $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * JSON for the x-magento-init script.
     *
     * @return string
     */
    public function getJsLayout(): string
    {
        $groups = $this->getCurrencyGroups();
        $codes = [];
        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $codes[] = $item['code'];
            }
        }

        $from = $this->pickDefault($this->config->getDefaultFrom(), $codes, 0);
        $to = $this->pickDefault($this->config->getDefaultTo(), $codes, 1);

        $component = [
            'component' => 'Tony_CurrencyConverter/js/converter',
            'template' => 'Tony_CurrencyConverter/converter',
            'currencyGroups' => $groups,
            'defaultFrom' => $from,
            'defaultTo' => $to,
            'ranges' => $this->historyRange->getCodes(),
            'defaultRange' => HistoryRange::DEFAULT_RANGE,
            'rateUrl' => $this->urlBuilder->getUrl('currency-converter/rate'),
            'historyUrl' => $this->urlBuilder->getUrl('currency-converter/history'),
            'unavailable' => $groups === [],
        ];

        return $this->json->serialize([
            '*' => [
                'Magento_Ui/js/core/app' => [
                    'components' => ['currencyConverter' => $component],
                ],
            ],
        ]);
    }

    /**
     * Currencies grouped by type, in a fixed group order.
     *
     * @return array<int, array{label: string, items: array<int, array<string, string>>}>
     */
    private function getCurrencyGroups(): array
    {
        try {
            $currencies = $this->exchangeRateService->getCurrencies();
        } catch (ProviderUnavailableException $e) {
            $this->logger->warning('Currency converter: currency list unavailable on page render.');
            return [];
        }

        $byType = [];
        foreach ($currencies as $currency) {
            $type = isset(self::GROUP_LABELS[$currency->getType()])
                ? $currency->getType()
                : CurrencyInterface::TYPE_CURRENCY;
            $byType[$type][] = [
                'code' => $currency->getCode(),
                'name' => $currency->getName(),
                'symbol' => $currency->getSymbol(),
                'label' => $currency->getCode() . ' — ' . $currency->getName(),
            ];
        }

        $groups = [];
        foreach (self::GROUP_LABELS as $type => $label) {
            if (!empty($byType[$type])) {
                $groups[] = ['label' => (string)__($label), 'items' => $byType[$type]];
            }
        }

        return $groups;
    }

    /**
     * Use the configured default if it exists, otherwise fall back to the n-th available code.
     *
     * @param string $preferred
     * @param string[] $codes
     * @param int $fallbackIndex
     *
     * @return string
     */
    private function pickDefault(string $preferred, array $codes, int $fallbackIndex): string
    {
        if (in_array($preferred, $codes, true)) {
            return $preferred;
        }

        return $codes[$fallbackIndex] ?? ($codes[0] ?? '');
    }
}
