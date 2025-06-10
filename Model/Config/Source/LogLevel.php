<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Monolog\Logger;

class LogLevel implements OptionSourceInterface
{
    /**
     * @return string[][]
     */
    #[\Override]
    public function toOptionArray(): array
    {
        return [
            ['value' => (string) Logger::INFO, 'label' => __('Everything')],
            ['value' => (string) Logger::ERROR, 'label' => __('Errors')],
        ];
    }
}
