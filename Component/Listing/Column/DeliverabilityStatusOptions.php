<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use PostDirekt\Addressfactory\Model\DeliverabilityStatus;

/**
 * Class DeliverabilityStatusOptions
 *
 * @author   Andreas Müller <andreas.mueller@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class DeliverabilityStatusOptions implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray(): array
    {
        $options = [
            [
                'value' => DeliverabilityStatus::NOT_ANALYSED,
                'label' => __('Not analysed')
            ],
            [
                'value' => DeliverabilityStatus::PENDING,
                'label' => __('Pending')
            ],
            [
                'value' => DeliverabilityStatus::UNDELIVERABLE,
                'label' => __('Undeliverable')
            ],
            [
                'value' => DeliverabilityStatus::POSSIBLY_DELIVERABLE,
                'label' => __('Possibly deliverable')
            ],
            [
                'value' => DeliverabilityStatus::DELIVERABLE,
                'label' => __('Deliverable')
            ],
            [
                'value' => DeliverabilityStatus::ADDRESS_CORRECTED,
                'label' => __('Address corrected')
            ],
            [
                'value' => DeliverabilityStatus::ANALYSIS_FAILED,
                'label' => __('Analysis failed')
            ]
        ];

        return $options;
    }
}
