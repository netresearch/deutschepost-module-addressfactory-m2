<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PostDirekt\Addressfactory\Model\ResourceModel\AnalysisStatus as AnalysisStatusResource;

class AnalysisStatusRepository
{
    /**
     * @var AnalysisStatusResource
     */
    private $resource;

    /**
     * @var AnalysisStatusFactory
     */
    private $analysisStatusFactory;

    public function __construct(
        AnalysisStatusResource $resource,
        AnalysisStatusFactory $analysisStatusFactory
    ) {
        $this->resource = $resource;
        $this->analysisStatusFactory = $analysisStatusFactory;
    }

    /**
     * @param int $orderId
     * @return AnalysisStatus
     * @throws NoSuchEntityException
     */
    public function getByOrderId(int $orderId): AnalysisStatus
    {
        $analysisStatus = $this->analysisStatusFactory->create();
        $this->resource->load($analysisStatus, $orderId);

        if (!$analysisStatus->getOrderId()) {
            throw new NoSuchEntityException(__('Analysis status with order id %1 does not exist.', $orderId));
        }

        return $analysisStatus;
    }

    /**
     * @param AnalysisStatus $analysisStatus
     * @return AnalysisStatus
     * @throws CouldNotSaveException
     */
    public function save(AnalysisStatus $analysisStatus): AnalysisStatus
    {
        try {
            $this->resource->save($analysisStatus);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__('Unable to save analysis status.'), $exception);
        }

        return $analysisStatus;
    }
}
