<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Controller\Adminhtml\Bulk;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use PostDirekt\Addressfactory\Model\OrderAnalysis;

class Improve extends Action
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

    public function __construct(
        Filter $filter,
        CollectionFactory $collectionFactory,
        OrderAnalysis $orderAnalysisService,
        Action\Context $context
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->orderAnalysisService = $orderAnalysisService;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        /**
         * Fetch orders selected via Mass Action
         */
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

        $updatedOrderIds = [];
        $failedOrderIds = [];
        foreach ($orders as $order) {
            $analysisResult = $analysisResults[(int) $order->getEntityId()];
            /* Try to update the shipping address of each order */
            $wasUpdated = $analysisResult
                ? $this->orderAnalysisService->updateShippingAddress($order, $analysisResult)
                : false;
            if ($wasUpdated) {
                $updatedOrderIds[] = $order->getIncrementId();
            } else {
                $failedOrderIds[] = $order->getIncrementId();
            }
        }

        if (!empty($updatedOrderIds)) {
            $this->messageManager->addSuccessMessage(
                __('Order(s) %1 were successfully updated.', implode(', ', $updatedOrderIds))
            );
        }
        if (!empty($failedOrderIds)) {
            $this->messageManager->addErrorMessage(
                __('Order(s) %1 could not be updated.', implode(', ', $updatedOrderIds))
            );
        }

        return $resultRedirect;
    }
}
