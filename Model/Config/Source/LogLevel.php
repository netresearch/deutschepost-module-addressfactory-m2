<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Monolog\Level;

class LogLevel implements OptionSourceInterface
{
    #[\Override]
    public function toOptionArray(): array
    {
        return [
            ['value' => (string) Level::Info->value, 'label' => __('Everything')],
            ['value' => (string) Level::Error->value, 'label' => __('Errors')],
        ];
    }
}
