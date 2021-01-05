<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Plugin\Sales;

use Magento\Sales\Model\ResourceModel\Order\Collection;

class OrderCollectionPlugin
{
    /**
     * Add field mapping for the order collection's status column.
     *
     * When the order collection is loaded, then the POSTDIREKT Addressfactory module adds some extension attributes.
     * The database table that holds the additional attributes also has a field `status`.
     * This leads to an integrity constraint/ambiguous column error. To fix this, we add the filter mapping.
     *
     * @param Collection $orderCollection
     * @return null
     */
    public function beforeAddFieldToFilter(Collection $orderCollection)
    {
        $orderCollection->addFilterToMap('status', 'main_table.status');
        return null;
    }
}
