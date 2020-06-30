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
    public const ADMIN_RESOURCE = 'Magento_Sales::actions_edit';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var OrderAnalysis
     */
    private $orderAnalysisService;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderUpdater
     */
    private $orderUpdater;

    public function __construct(
        Config $config,
        OrderAnalysis $orderAnalysisService,
        OrderRepositoryInterface $orderRepository,
        Context $context,
        OrderUpdater $orderUpdater
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->orderAnalysisService = $orderAnalysisService;
        $this->orderRepository = $orderRepository;
        $this->orderUpdater = $orderUpdater;
    }

    /**
     * Analyse and update the given order according to the Addressfactory configuration.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        /** @var Order $order */
        $order = $this->orderRepository->get($orderId);

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        try {
            $analysisResults = $this->orderAnalysisService->analyse([$order]);
            $analysisResult = $analysisResults[$orderId];
            if (!$analysisResult) {
                throw new LocalizedException(__('Could not perform ADDRESSFACTORY DIRECT analysis for Order'));
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
            return $resultRedirect;
        }

        if ($this->config->isHoldNonDeliverableOrders($order->getStoreId())) {
            $isOnHold = $this->orderUpdater->holdIfNonDeliverable($order, $analysisResult);
            if ($isOnHold) {
                $this->messageManager->addSuccessMessage(__('Non-deliverable Order put on hold'));
            }
        }
        if ($this->config->isAutoCancelNonDeliverableOrders($order->getStoreId())) {
            $isCanceled = $this->orderUpdater->cancelIfUndeliverable($order, $analysisResult);
            if ($isCanceled) {
                $this->messageManager->addSuccessMessage(__('Undeliverable Order canceled', $order->getIncrementId()));
            }
        }
        if ($this->config->isAutoUpdateShippingAddress($order->getStoreId())) {
            $isUpdated = $this->orderAnalysisService->updateShippingAddress($order, $analysisResult);
            if ($isUpdated) {
                $this->messageManager->addSuccessMessage(__('Order address updated with ADDRESSFACTORY DIRECT suggestion'));
            }
        }

        return $resultRedirect;
    }
}
