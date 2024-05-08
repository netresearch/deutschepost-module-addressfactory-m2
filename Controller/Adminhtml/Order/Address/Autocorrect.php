<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Controller\Adminhtml\Order\Address;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use PostDirekt\Addressfactory\Model\OrderAnalysis;

class Autocorrect extends Action
{
    /**
     * Authorization levels of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Sales::actions_edit';

    /**
     * @var OrderAnalysis
     */
    private $orderAnalysis;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        Context $context,
        OrderAnalysis $orderAnalysis,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderAnalysis = $orderAnalysis;
        $this->orderRepository = $orderRepository;

        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $orderId  = $this->getRequest()->getParam('order_id');
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);

        try {
            $analysisResults = $this->orderAnalysis->analyse([$order]);
            $analysisResult = $analysisResults[(int) $order->getEntityId()];
            if (!$analysisResult) {
                throw new LocalizedException(__('Could not perform ADDRESSFACTORY DIRECT analysis for Order'));
            }
            $wasUpdated = $this->orderAnalysis->updateShippingAddress($order, $analysisResult);
            if ($wasUpdated) {
                $this->messageManager->addSuccessMessage(__('Order address updated with ADDRESSFACTORY DIRECT suggestion'));
            } else {
                throw new LocalizedException(__('Could not update Order address with ADDRESSFACTORY DIRECT suggestion'));
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }
}
