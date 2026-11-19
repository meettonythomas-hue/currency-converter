<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Tony\CurrencyConverter\Model\Config;

/**
 * Storefront page: /currency-converter.
 */
class Index implements HttpGetActionInterface
{
    /**
     * Inject dependencies.
     *
     * @param PageFactory $pageFactory
     * @param ForwardFactory $forwardFactory
     * @param Config $config
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Config $config
    ) {
    }

    /**
     * Execute the action.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Currency Converter'));

        return $page;
    }
}
