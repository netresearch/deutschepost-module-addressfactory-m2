<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Controller\Adminhtml\Analysis;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
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
    public const string ADMIN_RESOURCE = 'Magento_Sales::actions_edit';

    public function __construct(
        Context $context,
        private Config $config,
        private OrderAnalysis $orderAnalysisService,
        private OrderRepositoryInterface $orderRepository,
        private OrderUpdater $orderUpdater
    ) {
        parent::__construct($context);
    }

    /**
     * Analyse and update the given order according to the Addressfactory configuration.
     *
     * @return ResultInterface
     */
    #[\Override]
    public function execute(): ResultInterface
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);

        /** @var \Magento\Framework\Controller\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        try {
            $analysisResults = $this->orderAnalysisService->analyse([$order]);
            $analysisResult = $analysisResults[$orderId];
            if (!$analysisResult) {
                throw new LocalizedException(__('Could not perform ADDRESSFACTORY DIRECT analysis for Order'));
            }

            $isCanceled = false;
            if ($this->config->isAutoCancelNonDeliverableOrders($order->getStoreId())) {
                $isCanceled = $this->orderUpdater->cancelIfUndeliverable($order, $analysisResult);
                if ($isCanceled) {
                    $this->messageManager->addSuccessMessage(
                        __('Undeliverable Order canceled', $order->getIncrementId())
                    );
                }
            }

            if ($this->config->isAutoUpdateShippingAddress($order->getStoreId())) {
                $isUpdated = $this->orderAnalysisService->updateShippingAddress($order, $analysisResult);
                if ($isUpdated) {
                    $this->messageManager->addSuccessMessage(
                        __('Order address updated with ADDRESSFACTORY DIRECT suggestion')
                    );
                }
            }

            if (!$isCanceled && $this->config->isHoldNonDeliverableOrders($order->getStoreId())) {
                $isOnHold = $this->orderUpdater->holdIfNonDeliverable($order, $analysisResult);
                if ($isOnHold) {
                    $this->messageManager->addSuccessMessage(__('Non-deliverable Order put on hold'));
                }
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $resultRedirect;
    }
}
