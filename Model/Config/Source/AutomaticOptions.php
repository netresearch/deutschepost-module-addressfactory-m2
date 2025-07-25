<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AutomaticOptions implements OptionSourceInterface
{
    public const NO_AUTOMATIC_ANALYSIS = '1';
    public const ANALYSIS_VIA_CRON = '2';
    public const ON_ORDER_PLACE = '3';

    #[\Override]
    public function toOptionArray(): array
    {
        return [
            ['value' => self::NO_AUTOMATIC_ANALYSIS, 'label' => __('No Automatic Analysis')],
            ['value' => self::ANALYSIS_VIA_CRON, 'label' => __('Analysis via Cron')],
            ['value' => self::ON_ORDER_PLACE, 'label' => __('Analysis on Order placement')],
        ];
    }
}
