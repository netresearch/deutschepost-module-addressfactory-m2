<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\ResourceModel\GridInterface;
use Psr\Log\LoggerInterface;

class AnalysisStatusUpdater
{
    public const NOT_ANALYSED = 'not_analysed';
    public const PENDING = 'pending';
    public const UNDELIVERABLE = 'undeliverable';
    public const CORRECTION_REQUIRED = 'correction_required';
    public const POSSIBLY_DELIVERABLE = 'possibly_deliverable';
    public const DELIVERABLE = 'deliverable';
    public const ADDRESS_CORRECTED = 'address_corrected';
    public const ANALYSIS_FAILED = 'analysis_failed';
    public const MANUALLY_EDITED = 'manually_edited';

    /**
     * @var AnalysisStatusRepository
     */
    private $repository;

    /**
     * @var AnalysisStatusFactory
     */
    private $statusFactory;

    /**
     * @var GridInterface
     */
    private $orderGrid;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        AnalysisStatusRepository $repository,
        AnalysisStatusFactory $analysisStatusFactory,
        GridInterface $orderGrid,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->repository = $repository;
        $this->statusFactory = $analysisStatusFactory;
        $this->orderGrid = $orderGrid;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Update analysis status in persistent storage and sales order grid.
     *
     * @param AnalysisStatus $status
     * @return bool
     */
    private function updateStatus(AnalysisStatus $status): bool
    {
        try {
            $this->repository->save($status);
            // if asynchronous grid indexing is disabled, grid data must be refreshed explicitly.
            if (!$this->scopeConfig->getValue('dev/grid/async_indexing')) {
                $this->orderGrid->refresh($status->getOrderId());
            }
        } catch (CouldNotSaveException $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return false;
        }

        return true;
    }

    public function setStatusPending(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::PENDING
        ]]);

        return $this->updateStatus($analysisStatus);
    }
    public function setStatusNotAnalyzed(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::NOT_ANALYSED
        ]]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusUndeliverable(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::UNDELIVERABLE
        ]]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusCorrectionRequired(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::CORRECTION_REQUIRED
        ]]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusPossiblyDeliverable(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::POSSIBLY_DELIVERABLE
        ]]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusDeliverable(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::DELIVERABLE
        ]]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusAddressCorrected(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::ADDRESS_CORRECTED
        ]]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusAnalysisFailed(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::ANALYSIS_FAILED
        ]]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusManuallyEdited(int $orderId): bool
    {
        $analysisStatus = $this->statusFactory->create(['data' => [
            AnalysisStatus::ORDER_ID => $orderId,
            AnalysisStatus::STATUS => self::MANUALLY_EDITED
        ]]);

        return $this->updateStatus($analysisStatus);
    }

    public function getStatus(int $orderId): string
    {
        try {
            $deliverabilityStatus = $this->repository->getByOrderId($orderId);
        } catch (NoSuchEntityException) {
            return self::NOT_ANALYSED;
        }

        return $deliverabilityStatus->getStatus();
    }

    public function isStatusCorrectable(string $status): bool
    {
        $correctableStatuses = [
            self::ANALYSIS_FAILED,
            self::UNDELIVERABLE,
            self::POSSIBLY_DELIVERABLE,
            self::DELIVERABLE,
            self::CORRECTION_REQUIRED,
            self::MANUALLY_EDITED
        ];

        return \in_array($status, $correctableStatuses, true);
    }
}
