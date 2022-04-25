<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterfaceFactory;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisResult as AnalysisResultResource;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisResult\SearchResult;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisResult\SearchResultFactory;

class AnalysisResultRepository
{
    /**
     * @var AnalysisResultResource
     */
    private $resource;

    /**
     * @var AnalysisResultInterfaceFactory
     */
    private $analysisResultFactory;

    /**
     * @var SearchResultFactory
     */
    private $searchResultFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private $collectionProcessor;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        AnalysisResultResource $resource,
        AnalysisResultInterfaceFactory $analysisResultFactory,
        SearchResultFactory $searchResultFactory,
        CollectionProcessorInterface $collectionProcessor,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->resource = $resource;
        $this->analysisResultFactory = $analysisResultFactory;
        $this->searchResultFactory = $searchResultFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param int $addressId
     * @return AnalysisResultInterface
     * @throws NoSuchEntityException
     */
    public function getByAddressId(int $addressId): AnalysisResultInterface
    {
        $analysisResult = $this->analysisResultFactory->create();
        $this->resource->load($analysisResult, $addressId);

        if (!$analysisResult->getOrderAddressId()) {
            throw new NoSuchEntityException(__('Analysis result with order address id %1 does not exist.', $addressId));
        }

        return $analysisResult;
    }

    /**
     * @param int[] $addressIds
     * @return AnalysisResultInterface[]
     */
    public function getListByAddressIds(array $addressIds): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(AnalysisResultInterface::ORDER_ADDRESS_ID, $addressIds, 'in')
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @param SearchCriteria $searchCriteria
     * @return SearchResult
     */
    public function getList(SearchCriteria $searchCriteria): SearchResult
    {
        $searchResult = $this->searchResultFactory->create();
        $this->collectionProcessor->process($searchCriteria, $searchResult);
        $searchResult->setSearchCriteria($searchCriteria);

        return $searchResult;
    }

    /**
     * @param AnalysisResultInterface $analysisResult
     * @return AnalysisResultInterface
     * @throws CouldNotSaveException
     */
    public function save(AnalysisResultInterface $analysisResult): AnalysisResultInterface
    {
        try {
            $this->resource->save($analysisResult);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__('Unable to save analysis result.'), $exception);
        }

        return $analysisResult;
    }

    /**
     * @param AnalysisResultInterface[] $analysisResults
     * @return SearchResult
     * @throws CouldNotSaveException
     */
    public function saveList(array $analysisResults): SearchResult
    {
        $searchResult = $this->searchResultFactory->create();
        try {
            // add new records to collection which will be persisted
            $searchResult->setItems($analysisResults);
            $searchResult->save();
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__('Unable to save analysis results'), $exception);
        }

        return $searchResult;
    }

    /**
     * @param AnalysisResultInterface $analysisResult
     * @throws CouldNotDeleteException
     */
    public function delete(AnalysisResultInterface $analysisResult): void
    {
        try {
            $this->resource->delete($analysisResult);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete analysis result for order address id: %1', $analysisResult->getOrderAddressId()),
                $exception
            );
        }
    }
}
