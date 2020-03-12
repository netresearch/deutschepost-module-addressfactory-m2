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
            $orderCollection = $this->filter->getCollection($this->collectionFactory->create());
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
        $msg = __('Order(s) %1 where successfully updated.', implode(', ', $incrementIds));
        $this->messageManager->addSuccessMessage($msg);

        return $resultRedirect;
    }
}
