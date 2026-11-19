<?php
/**
 * Copyright © Tony. All rights reserved.
 */
declare(strict_types=1);

namespace Tony\CurrencyConverter\Block;

use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Framework\View\Element\Template\Context;
use Tony\CurrencyConverter\Model\Config;

/**
 * Footer link that is only rendered when the page is enabled and the link is switched on.
 */
class FooterLink extends Current
{
    /**
     * Inject dependencies.
     *
     * @param Context $context
     * @param DefaultPathInterface $defaultPath
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * Render the link only when the page and the footer link are enabled.
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->config->isEnabled() || !$this->config->isFooterLinkShown()) {
            return '';
        }

        return parent::_toHtml();
    }
}
