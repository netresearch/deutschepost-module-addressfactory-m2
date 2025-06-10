<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model\ResourceModel\AnalysisResult;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PostDirekt\Addressfactory\Api\Data\AnalysisResult\SearchResultInterface;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisResult as AnalysisResultResource;

class SearchResult extends AbstractCollection implements SearchResultInterface
{
    /**
     * @var SearchCriteriaInterface
     */
    private $searchCriteria;

    #[\Override]
    protected function _construct()
    {
        $this->_init(AnalysisResult::class, AnalysisResultResource::class);
    }

    /**
     * Get search criteria.
     *
     * @return SearchCriteriaInterface|null
     */
    #[\Override]
    public function getSearchCriteria(): ? SearchCriteriaInterface
    {
        return $this->searchCriteria;
    }

    /**
     * Set search criteria.
     *
     * @param SearchCriteriaInterface|null $searchCriteria
     * @return $this
     */
    #[\Override]
    public function setSearchCriteria(?SearchCriteriaInterface $searchCriteria = null): SearchResultInterface
    {
        $this->searchCriteria = $searchCriteria;
        return $this;
    }

    /**
     * Get total count.
     *
     * @return int
     */
    #[\Override]
    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    /**
     * Not applicable, Collection vs. Search Result seems to be work in progress.
     *
     * @param int $totalCount
     * @return $this
     */
    #[\Override]
    public function setTotalCount($totalCount): SearchResultInterface
    {
        return $this;
    }

    /**
     * Set items list.
     *
     * @param AnalysisResultInterface[] $items
     * @return $this
     * @throws \Exception
     */
    #[\Override]
    public function setItems(?array $items = null): SearchResultInterface
    {
        if (!$items) {
            return $this;
        }
        foreach ($items as $item) {
            $this->addItem($item);
        }

        return $this;
    }
}
