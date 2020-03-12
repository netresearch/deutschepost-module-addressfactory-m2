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
use PostDirekt\Addressfactory\Model\Config;
use PostDirekt\Addressfactory\Model\OrderAnalysis;

/**
 * AddressAnalysis
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
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

    public function __construct(
        Config $config,
        OrderAnalysis $orderAnalysisService,
        OrderRepositoryInterface $orderRepository,
        Context $context
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->orderAnalysisService = $orderAnalysisService;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Analyse and update the given order according to the Addressfactory configuration.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $order = $this->orderRepository->get($orderId);
        $previousOrderState = $order->getState();

        try {
            $this->orderAnalysisService->analyse([$order]);
            if ($this->config->isHoldNonDeliverableOrders($order->getStoreId())) {
                $this->orderAnalysisService->holdNonDeliverable([$order]);
            }
            if ($this->config->isAutoCancelNonDeliverableOrders($order->getStoreId())) {
                $this->orderAnalysisService->cancelUndeliverable([$order]);
            }
            if ($this->config->isAutoUpdateShippingAddress($order->getStoreId())) {
                $this->orderAnalysisService->updateShippingAddress([$order]);
                $msg =  __('Updated shipping address for order #%1', $order->getIncrementId());
                $this->messageManager->addSuccessMessage($msg);
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        if ($order->getState() !== $previousOrderState) {
            $msg = __('Order state changed to "%1"', $order->getState());
            $this->messageManager->addSuccessMessage($msg);
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }
}
