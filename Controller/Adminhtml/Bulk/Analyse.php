<?php

namespace PostDirekt\Addressfactory\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use PostDirekt\Addressfactory\Model\Config;
use PostDirekt\Addressfactory\Model\OrderAnalysis;
use PostDirekt\Addressfactory\Model\OrderUpdater;

class Analyse extends Action
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

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        try {
            $orders = $this->getOrders();
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $resultRedirect;
        }

        /**
         * Perform deliverability analysis for all orders (or fetch from DB)
         */
        $analysisResults = $this->orderAnalysisService->analyse($orders);

        $updatedOrderIds = [];
        $failedOrderIds = [];
        $canceledOrderIds = [];
        $heldOrderIds = [];
        foreach ($orders as $order) {
            $analysisResult = $analysisResults[(int) $order->getEntityId()];
            if (!$analysisResult) {
                $failedOrderIds[] = $order->getIncrementId();
                continue;
            }

            $isCanceled = false;
            if ($this->moduleConfig->isAutoCancelNonDeliverableOrders($order->getStoreId())) {
                $isCanceled = $this->orderUpdater->cancelIfUndeliverable($order, $analysisResult);
                if ($isCanceled) {
                    $canceledOrderIds[] = $order->getIncrementId();
                }
            }

            if ($this->moduleConfig->isAutoUpdateShippingAddress($order->getStoreId())) {
                if ($this->orderAnalysisService->updateShippingAddress($order, $analysisResult)) {
                    $updatedOrderIds[] = $order->getIncrementId();
                }
            }

            if (!$isCanceled && $this->moduleConfig->isHoldNonDeliverableOrders($order->getStoreId())) {
                if ($this->orderUpdater->holdIfNonDeliverable($order, $analysisResult)) {
                    $heldOrderIds[] = $order->getIncrementId();
                }
            }
        }
        if (!empty($updatedOrderIds)) {
            $this->messageManager->addSuccessMessage(
                __('Order(s) %1 were successfully updated.', implode(', ', $updatedOrderIds))
            );
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

    /**
     * @return Order[]
     * @throws LocalizedException
     */
    private function getOrders(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->join(
            'sales_order_address',
            'main_table.entity_id=sales_order_address.parent_id',
            ['address_type', 'country_id']
        );
        $collection->addAttributeToFilter('sales_order_address.address_type', 'shipping');
        $collection->addAttributeToFilter('sales_order_address.country_id', 'DE');
        return $this->filter->getCollection($collection)->getItems();
    }
}
