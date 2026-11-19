<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Controller\Rate;

use Tony\CurrencyConverter\Controller\AbstractJsonAction;

/**
 * GET /currency-converter/rate?from=USD&to=EUR
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
        $rate = $this->exchangeRateService->getRate(
            $this->getStringParam('from'),
            $this->getStringParam('to')
        );

        return [
            'from' => $rate->getFrom(),
            'to' => $rate->getTo(),
            'rate' => $rate->getRate(),
            'date' => $rate->getDate(),
        ];
    }
}
