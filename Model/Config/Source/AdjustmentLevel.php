<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Logger\Monolog;


/**
 * todo(nr): this gonna be removed beacuse the API does not support it.
 * Class AdjustmentLevel
 *
 * @author   Gurjit Singh <gurjit.singh@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class AdjustmentLevel implements OptionSourceInterface
{
    /**
     * @return string[][]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '1', 'label' => __('Weak')],
            ['value' => '2', 'label' => __('Medium')],
            ['value' => '3', 'label' => __('Strong')],
        ];
    }
}
