<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
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
     * AnalysisResultRepository constructor.
     * @param AnalysisResultResource $resource
     * @param AnalysisResultFactory $analysisResultFactory
     */
    public function __construct(AnalysisResultResource $resource, AnalysisResultFactory $analysisResultFactory)
    {
        $this->resource = $resource;
        $this->analysisResultFactory = $analysisResultFactory;
    }

    /**
     * @param string $addressId
     * @return AnalysisResult
     * @throws NoSuchEntityException
     */
    public function getByAddressId(string $addressId): AnalysisResult
    {
        $analysisResult = $this->analysisResultFactory->create();
        $this->resource->load($analysisResult, $addressId);

        if (!$analysisResult->getOrderAddressId()) {
            throw new NoSuchEntityException(__('Analysis result with order address id %1 does not exist.', $addressId));
        }

        return $analysisResult;
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
}
