<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Logger\Monolog;

class LogLevel implements OptionSourceInterface
{
    /**
     * @return string[][]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => (string) Monolog::INFO, 'label' => __('Everything')],
            ['value' => (string) Monolog::ERROR, 'label' => __('Errors')],
        ];
    }
}
