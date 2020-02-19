<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisResult\SearchResult;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisResult\SearchResultFactory;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisResult as AnalysisResultResource;

/**
 * AnalysisResult Repository
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
class AnalysisResultRepository
{
    /**
     * @var AnalysisResultResource
     */
    private $resource;

    /**
     * @var AnalysisResultFactory
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

    public function __construct(
        AnalysisResultResource $resource,
        AnalysisResultFactory $analysisResultFactory,
        SearchResultFactory $searchResultFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->analysisResultFactory = $analysisResultFactory;
        $this->searchResultFactory = $searchResultFactory;
        $this->collectionProcessor = $collectionProcessor;
    }


    /**
     * @param int $addressId
     * @return AnalysisResult
     * @throws NoSuchEntityException
     */
    public function getByAddressId(int $addressId): AnalysisResult
    {
        $analysisResult = $this->analysisResultFactory->create();
        $this->resource->load($analysisResult, $addressId);

        if (!$analysisResult->getOrderAddressId()) {
            throw new NoSuchEntityException(__('Analysis result with order address id %1 does not exist.', $addressId));
        }

        return $analysisResult;
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
     * @param AnalysisResult $analysisResult
     * @return AnalysisResult
     * @throws CouldNotSaveException
     */
    public function save(AnalysisResult $analysisResult): AnalysisResult
    {
        try {
            $this->resource->save($analysisResult);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__('Unable to save analysis result.'), $exception);
        }

        return $analysisResult;
    }

    /**
     * @param AnalysisResult[] $analysisResults
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
}
