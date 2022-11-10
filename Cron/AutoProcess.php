<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Cron;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PostDirekt\Addressfactory\Model\AnalysisResult;
use PostDirekt\Addressfactory\Model\AnalysisStatus;
use PostDirekt\Addressfactory\Model\AnalysisStatusUpdater;
use PostDirekt\Addressfactory\Model\Config;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Model\OrderUpdater;
use Psr\Log\LoggerInterface;

class AutoProcess
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var OrderAnalysis
     */
    private $orderAnalysisService;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    private $searchCriteriaBuilderFactory;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        OrderAnalysis $orderAnalysisService,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        OrderRepositoryInterface $orderRepository,
        OrderUpdater $orderUpdater,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->orderAnalysisService = $orderAnalysisService;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->orderRepository = $orderRepository;
        $this->orderUpdater = $orderUpdater;
        $this->logger = $logger;
    }

    /**
     * Executed via Cron to analyse and process all new Orders that have been put into analysis status "pending" or
     * optional status "manually_edited"
     */
    public function execute(): void
    {
        if (!$this->config->isAnalysisViaCron()) {
            return;
        }
        /** @var Order[] $orders */
        $orders = $this->loadPendingOrManuallyEditedOrders();
        $analysisResults = $this->orderAnalysisService->analyse($orders);

        foreach ($orders as $order) {
            $analysisResult = $analysisResults[(int) $order->getEntityId()];
            if ($analysisResult) {
                $this->process($order, $analysisResult);
            } else {
                $this->logger->error(
                    sprintf('ADDRESSFACTORY DIRECT: Order %s could not be analysed', $order->getIncrementId())
                );
            }
        }
    }

    /**
     * Process given order according to the module configuration:
     *
     * - put it on hold,
     * - cancel it or
     * - update the shipping address
     *
     * @param Order $order
     * @param AnalysisResult $analysisResult
     */
    private function process(Order $order, AnalysisResult $analysisResult): void
    {
        $this->logger->info(
            sprintf('ADDRESSFACTORY DIRECT: Processing Order %s ...', $order->getIncrementId())
        );

        $isCanceled = false;
        if ($this->config->isAutoCancelNonDeliverableOrders($order->getStoreId())) {
            $isCanceled = $this->orderUpdater->cancelIfUndeliverable($order, $analysisResult);
            if ($isCanceled) {
                $this->logger->info(
                    sprintf(
                        'ADDRESSFACTORY DIRECT: Undeliverable Order "%s" cancelled',
                        $order->getIncrementId()
                    )
                );
            }
        }

        if ($this->config->isAutoUpdateShippingAddress($order->getStoreId())) {
            $isUpdated = $this->orderAnalysisService->updateShippingAddress($order, $analysisResult);
            if ($isUpdated) {
                $this->logger->info(
                    sprintf(
                        'ADDRESSFACTORY DIRECT: ADDRESSFACTORY DIRECT: Order "%s" address updated',
                        $order->getIncrementId()
                    )
                );
            }
        }

        if (!$isCanceled && $this->config->isHoldNonDeliverableOrders($order->getStoreId())) {
            $isOnHold = $this->orderUpdater->holdIfNonDeliverable($order, $analysisResult);
            if ($isOnHold) {
                $this->logger->info(
                    sprintf(
                        'ADDRESSFACTORY DIRECT: Non-deliverable Order "%s" put on hold',
                        $order->getIncrementId()
                    )
                );
            }
        }
    }

    /**
     * Fetch all orders with analysis status "pending" or
     * "manually_edited" if config is set from the database.
     *
     * @return OrderInterface[]
     */
    private function loadPendingOrManuallyEditedOrders(): array
    {
        $pendingFilter = $this->filterBuilder->setField('status_table.' . AnalysisStatus::STATUS)
            ->setValue(AnalysisStatusUpdater::PENDING)
            ->setConditionType('eq')
            ->create();
        $this->filterGroupBuilder->addFilter($pendingFilter);

        if ($this->config->isAutoValidateManuallyEdited()) {
            $manuallyEditedFilter = $this->filterBuilder->setField('status_table.' . AnalysisStatus::STATUS)
                ->setValue(AnalysisStatusUpdater::MANUALLY_EDITED)
                ->setConditionType('eq')
                ->create();
            $this->filterGroupBuilder->addFilter($manuallyEditedFilter);
        }

        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->setFilterGroups([$this->filterGroupBuilder->create()]);
        $searchCriteria = $searchCriteriaBuilder->create();

        $collection = $this->orderRepository->getList($searchCriteria);

        return $collection->getItems();
    }
}
