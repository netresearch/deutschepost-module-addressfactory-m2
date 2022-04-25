<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use PostDirekt\Addressfactory\Model\Config;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Model\OrderUpdater;

class Check extends Action
{
    /**
     * Authorization levels of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Sales::actions_edit';

    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var OrderAnalysis
     */
    private $orderAnalysisService;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    /**
     * @var Config
     */
    private $moduleConfig;

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        OrderAnalysis $orderAnalysisService,
        Config $moduleConfig,
        OrderUpdater $orderUpdater
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->orderAnalysisService = $orderAnalysisService;
        $this->moduleConfig = $moduleConfig;
        $this->orderUpdater = $orderUpdater;

        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        try {
            $collection = $this->collectionFactory->create();
            $collection->join(
                'sales_order_address',
                'main_table.entity_id=sales_order_address.parent_id',
                ['address_type', 'country_id']
            );
            $collection->addAttributeToFilter('sales_order_address.address_type', 'shipping');
            $collection->addAttributeToFilter('sales_order_address.country_id', 'DE');
            $orderCollection = $this->filter->getCollection($collection);
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $resultRedirect;
        }

        /** @var Order[] $orders */
        $orders = $orderCollection->getItems();

        /**
         * Perform deliverability analysis for all orders (or fetch from DB)
         */
        $analysisResults = $this->orderAnalysisService->analyse($orders);

        $heldOrderIds = [];
        $canceledOrderIds = [];
        $failedOrderIds = [];
        foreach ($orders as $order) {
            $analysisResult = $analysisResults[(int) $order->getEntityId()];
            if (!$analysisResult) {
                $failedOrderIds[] = $order->getIncrementId();
                continue;
            }
            if ($this->moduleConfig->isHoldNonDeliverableOrders()) {
                $isOnHold = $this->orderUpdater->holdIfNonDeliverable($order, $analysisResult);
                if ($isOnHold) {
                    $heldOrderIds[] = $order->getIncrementId();
                }
            }
            if ($this->moduleConfig->isAutoCancelNonDeliverableOrders()) {
                $isCanceled = $this->orderUpdater->cancelIfUndeliverable($order, $analysisResult);
                if ($isCanceled) {
                    $canceledOrderIds[] = $order->getIncrementId();
                }
            }
        }

        if (!empty($heldOrderIds)) {
            $this->messageManager->addSuccessMessage(
                __('Non-deliverable Order(s) %1 were put on hold.', implode(', ', $heldOrderIds))
            );
        }
        if (!empty($canceledOrderIds)) {
            $this->messageManager->addSuccessMessage(
                __('Undeliverable Order(s) %1 were canceled.', implode(', ', $canceledOrderIds))
            );
        }
        if (!empty($failedOrderIds)) {
            $this->messageManager->addErrorMessage(
                __('Order(s) %1 could not be analysed with ADDRESSFACTORY DIRECT.', implode(', ', $failedOrderIds))
            );
        }

        return $resultRedirect;
    }
}
