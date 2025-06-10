<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Component\Listing\Column;

use Magento\Framework\Data\OptionSourceInterface;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;

class AnalysisStatusOptions implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    #[\Override]
    public function toOptionArray(): array
    {
        return [
            [
                'value' => AnalysisStatusUpdater::NOT_ANALYSED,
                'label' => __('Not checked')
            ],
            [
                'value' => AnalysisStatusUpdater::PENDING,
                'label' => __('Pending')
            ],
            [
                'value' => AnalysisStatusUpdater::UNDELIVERABLE,
                'label' => __('Undeliverable')
            ],
            [
                'value' => AnalysisStatusUpdater::POSSIBLY_DELIVERABLE,
                'label' => __('Possibly deliverable')
            ],
            [
                'value' => AnalysisStatusUpdater::DELIVERABLE,
                'label' => __('Deliverable')
            ],
            [
                'value' => AnalysisStatusUpdater::ADDRESS_CORRECTED,
                'label' => __('Address corrected')
            ],
            [
                'value' => AnalysisStatusUpdater::ANALYSIS_FAILED,
                'label' => __('Failed')
            ],
            [
                'value' => AnalysisStatusUpdater::CORRECTION_REQUIRED,
                'label' => __('Correction recommended')
            ],
            [
                'value' => AnalysisStatusUpdater::MANUALLY_EDITED,
                'label' => __('Manually edited')
            ]
        ];
    }
}
