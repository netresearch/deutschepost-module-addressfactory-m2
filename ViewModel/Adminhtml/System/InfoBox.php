<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\ViewModel\Adminhtml\System;

use PostDirekt\Addressfactory\Model\Config;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class InfoBox implements ArgumentInterface
{
    public function __construct(private Config $config)
    {
    }

    /**
     * Obtain the Module Version from Config.
     *
     * @return string
     */
    public function getModuleVersion(): string
    {
        return $this->config->getModuleVersion();
    }
}
