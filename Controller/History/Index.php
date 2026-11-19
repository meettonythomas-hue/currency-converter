<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Controller\History;

use Tony\CurrencyConverter\Controller\AbstractJsonAction;

/**
 * GET /currency-converter/history?from=USD&to=EUR&range=3M
 */
class Index extends AbstractJsonAction
{
    /**
     * Build the JSON payload for this endpoint.
     *
     * @return array
     */
    protected function getPayload(): array
    {
        $range = strtoupper($this->getStringParam('range'));
        $points = $this->exchangeRateService->getHistory(
            $this->getStringParam('from'),
            $this->getStringParam('to'),
            $range
        );

        $series = [];
        foreach ($points as $point) {
            $series[] = ['date' => $point->getDate(), 'rate' => $point->getRate()];
        }

        return [
            'from' => strtoupper(trim($this->getStringParam('from'))),
            'to' => strtoupper(trim($this->getStringParam('to'))),
            'range' => $range,
            'points' => $series,
        ];
    }
}
