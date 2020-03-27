<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Api\Data\AnalysisResult;

use Magento\Framework\Api\SearchResultsInterface;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;

/**
 * AnalysisSearchResultInterface
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
interface SearchResultInterface extends SearchResultsInterface
{
    /**
     * Get Event list.
     *
     * @return AnalysisResultInterface[]
     */
    public function getItems();

    /**
     * Set event_id list.
     *
     * @param AnalysisResultInterface[] $items
     * @return $this
     */
    public function setItems(array $items): SearchResultInterface;
}
