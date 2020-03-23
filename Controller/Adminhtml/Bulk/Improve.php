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
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use PostDirekt\Addressfactory\Model\OrderAnalysis;

/**
 * Bulk Improve Address
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
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
            $this->orderAnalysisService->updateShippingAddress($orderCollection->getItems());
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $resultRedirect;
        }

        $incrementIds = [];
        /** @var OrderInterface $order */
        foreach ($orderCollection->getItems() as $order) {
            $incrementIds[] = $order->getIncrementId();
        }
        if (empty($incrementIds)) {
            $msg = __('No Orders were updated.');
        } else {
            $msg = __('Order(s) %1 were successfully updated.', implode(', ', $incrementIds));
        }
        $this->messageManager->addSuccessMessage($msg);

        return $resultRedirect;
    }
}
