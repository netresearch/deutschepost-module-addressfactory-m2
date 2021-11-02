<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Sales\Api\Data\OrderInterface;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisStatus as AnalysisStatusResource;

class AnalysisStatusJoinProcessor implements CollectionProcessorInterface
{

    /**
     * @var AnalysisStatusResource
     */
    private $analysisStatusResource;

    public function __construct(AnalysisStatusResource $analysisStatusResource)
    {
        $this->analysisStatusResource = $analysisStatusResource;
    }

    /**
     * Add capability to filter orders by analysis status.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @param AbstractDb $collection
     */
    public function process(SearchCriteriaInterface $searchCriteria, AbstractDb $collection): void
    {
        $tableName = $this->analysisStatusResource->getTable('postdirekt_addressfactory_analysis_status');

        $collection->getSelect()->joinLeft(
            [
                'status_table' => $tableName
            ],
            sprintf(
                'main_table.%s = status_table.%s',
                OrderInterface::ENTITY_ID,
                AnalysisStatus::ORDER_ID
            ),
            []
        );
    }
}
