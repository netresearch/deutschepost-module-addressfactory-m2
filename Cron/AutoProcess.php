<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Cron;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
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
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterGroupBuilder
     */
    private $filterGroupBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

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
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterGroupBuilder $filterGroupBuilder,
        FilterBuilder $filterBuilder,
        OrderRepositoryInterface $orderRepository,
        OrderUpdater $orderUpdater,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->orderAnalysisService = $orderAnalysisService;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
        $this->filterBuilder = $filterBuilder;
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
        /** @var Order[] $orders */
        $orders = $this->loadPendingOrManuallyEditedOrders();
        if (empty($orders)) {
            return;
        }

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
     * "manually_edited" and where config is enabled for website.
     *
     * @return OrderInterface[]
     */
    private function loadPendingOrManuallyEditedOrders(): array
    {
        $autoProcessStores = $this->config->getStoresWithCronAnalysisEnabled();
        if (empty($autoProcessStores)) {
            return [];
        }

        $inclManuallyEdited = array_filter(
            $autoProcessStores,
            fn(int $storeId) => $this->config->isAutoValidateManuallyEdited($storeId)
        );

        $exclManuallyEdited = array_diff($autoProcessStores, $inclManuallyEdited);

        if (empty($inclManuallyEdited)) {
            // collect all pending orders from the stores with cron validation enabled
            $this->searchCriteriaBuilder->addFilter('store_id', $autoProcessStores, 'in');
            $this->searchCriteriaBuilder->addFilter(
                'status_table.' . AnalysisStatus::STATUS,
                AnalysisStatusUpdater::PENDING
            );
        } elseif (empty($exclManuallyEdited)) {
            // collect all pending or manually edited orders from the stores with cron validation enabled
            $this->searchCriteriaBuilder->addFilter('store_id', $autoProcessStores, 'in');
            $this->searchCriteriaBuilder->addFilter(
                'status_table.' . AnalysisStatus::STATUS,
                [AnalysisStatusUpdater::PENDING, AnalysisStatusUpdater::MANUALLY_EDITED],
                'in'
            );
        } else {
            // collect all pending orders from the stores with cron validation enabled plus manually edited orders
            // from stores with both, cron validation and re-validation of manually edited analysis results enabled.
            $this->searchCriteriaBuilder->setFilterGroups(
                [
                    $this->filterGroupBuilder
                        ->setFilters(
                            [
                                $this->filterBuilder
                                    ->setField('store_id')
                                    ->setValue($autoProcessStores)
                                    ->setConditionType('in')
                                    ->create(),
                            ]
                        )
                        ->create(),
                    $this->filterGroupBuilder
                        ->setFilters(
                            [
                                $this->filterBuilder
                                    ->setField('store_id')
                                    ->setValue($exclManuallyEdited)
                                    ->setConditionType('in')
                                    ->create(),
                                $this->filterBuilder
                                    ->setField('status_table.' . AnalysisStatus::STATUS)
                                    ->setValue([AnalysisStatusUpdater::PENDING, AnalysisStatusUpdater::MANUALLY_EDITED])
                                    ->setConditionType('in')
                                    ->create(),
                            ]
                        )
                        ->create(),
                    $this->filterGroupBuilder
                        ->setFilters(
                            [
                                $this->filterBuilder
                                    ->setField('store_id')
                                    ->setValue($inclManuallyEdited)
                                    ->setConditionType('in')
                                    ->create(),
                                $this->filterBuilder
                                    ->setField('status_table.' . AnalysisStatus::STATUS)
                                    ->setValue([AnalysisStatusUpdater::PENDING])
                                    ->setConditionType('in')
                                    ->create(),
                            ]
                        )
                        ->create(),
                ]
            );
        }

        $searchResult = $this->orderRepository->getList($this->searchCriteriaBuilder->create());
        return $searchResult->getItems();
    }
}
