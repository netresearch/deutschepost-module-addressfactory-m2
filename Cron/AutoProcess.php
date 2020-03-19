<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Cron;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use PostDirekt\Addressfactory\Model\AnalysisStatus;
use PostDirekt\Addressfactory\Model\Config;
use PostDirekt\Addressfactory\Model\DeliverabilityStatus;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use Psr\Log\LoggerInterface;

/**
 * Class AutoProcess
 *
 * @author   Andreas Müller <andreas.mueller@netresearch.de>
 * @link     https://www.netresearch.de/
 */
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
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        OrderAnalysis $orderAnalysisService,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        FilterBuilder $filterBuilder,
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->orderAnalysisService = $orderAnalysisService;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->filterBuilder = $filterBuilder;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Executed via Cron to process all new Orders that have been put into status "pending"
     */
    public function execute(): void
    {
        if (!$this->config->isAnalysisViaCron()) {
            return;
        }
        $orders = $this->loadOrders();
        $this->logger->info(
            sprintf(
                'ADDRESSFACTORY DIRECT: Processing Order(s) %s ...',
                $this->getIdList($orders)
            )
        );

        $this->process($orders);
    }

    /**
     * Analyse every given order and
     *
     * - put it on hold,
     * - cancel it or
     * - update the shipping address
     *
     * according to the module configuration.
     *
     * @param OrderInterface[] $orders
     */
    private function process(array $orders): void
    {
        /** @var OrderInterface[][] $scopes Orders sorted by storeId */
        $scopes = [];
        foreach ($orders as $order) {
            $scopes[$order->getStoreId()][] = $order;
        }

        try {
            $this->orderAnalysisService->analyse($orders);
        } catch (LocalizedException $exception) {
            $msg = sprintf(
                'ADDRESSFACTORY DIRECT: Order(s) %s could not be analysed, skipping',
                $this->getIdList($orders)
            );
            $this->logger->error($msg, ['exception' => $exception]);
            return;
        }

        foreach ($scopes as $scope => $scopedOrders) {
            $this->logger->info("ADDRESSFACTORY DIRECT: Processing Orders from scope id '$scope' ...");
            try {
                if ($this->config->isHoldNonDeliverableOrders($scope)) {
                    $heldOrders = $this->orderAnalysisService->holdNonDeliverable($scopedOrders);
                    $msg = sprintf(
                        'ADDRESSFACTORY DIRECT: Order(s) %s cancelled',
                        $this->getIdList($heldOrders)
                    );
                    $this->logger->info($msg);
                }
                if ($this->config->isAutoCancelNonDeliverableOrders($scope)) {
                    $cancelledOrders = $this->orderAnalysisService->cancelUndeliverable($scopedOrders);
                    $msg = sprintf(
                        'ADDRESSFACTORY DIRECT: Order(s) %s cancelled',
                        $this->getIdList($cancelledOrders)
                    );
                    $this->logger->info($msg);
                }
                if ($this->config->isAutoUpdateShippingAddress($scope)) {
                    $this->orderAnalysisService->updateShippingAddress($scopedOrders);
                    $this->logger->info(
                        sprintf(
                            'ADDRESSFACTORY DIRECT: Order(s) %s shipping addresses updated',
                            $this->getIdList($scopedOrders)
                        )
                    );
                }
            } catch (CouldNotSaveException|LocalizedException $exception) {
                $msg = sprintf(
                    'ADDRESSFACTORY DIRECT: Order(s) %s could not be processed',
                    $this->getIdList($scopedOrders)
                );
                $this->logger->error($msg, ['exception' => $exception]);
            }
            $msg = sprintf(
                'ADDRESSFACTORY DIRECT: Order(s) %s were successfully processed!',
                $this->getIdList($scopedOrders)
            );
            $this->logger->info($msg);
        }
    }

    /**
     * Fetch all orders with analysis status "pending" from the database.
     *
     * @return OrderInterface[]
     */
    private function loadOrders(): array
    {
        $pendingFilter = $this->filterBuilder->setField('status_table.' . AnalysisStatus::STATUS)
            ->setValue(DeliverabilityStatus::PENDING)
            ->setConditionType('eq')
            ->create();

        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilters([$pendingFilter]);
        $searchCriteria = $searchCriteriaBuilder->create();

        try {
            $collection = $this->orderRepository->getList($searchCriteria);
        } catch (\Zend_Db_Exception $exception) {
            $this->logger->error(
                'ADDRESSFACTORY DIRECT: Could not load Orders for auto processing.',
                ['exception' => $exception]
            );
            return [];
        }

        return $collection->getItems();
    }

    /**
     * Get a comma-separated list of all Order increment id's for convenient logging.
     *
     * @param OrderInterface[] $orders
     * @return string
     */
    private function getIdList(array $orders): string
    {
        return implode(
            ', ',
            array_map(
                static function (OrderInterface $order) {
                    return $order->getIncrementId();
                },
                $orders
            )
        );
    }
}
