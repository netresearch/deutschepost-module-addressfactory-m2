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
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use PostDirekt\Addressfactory\Model\OrderAnalysis;

/**
 * Class Autocorrect
 *
 * @author   Andreas Müller <andreas.mueller@netresearch.de>
 * @link     https://www.netresearch.de/
 */
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
    private $orderAnalyse;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        OrderAnalysis $orderAnalyse,
        OrderRepositoryInterface $orderRepository,
        Context $context
    ) {
        parent::__construct($context);
        $this->orderAnalyse = $orderAnalyse;
        $this->orderRepository = $orderRepository;
    }

    public function execute(): ResultInterface
    {
        $orderId  = $this->getRequest()->getParam('order_id');
        $order = $this->orderRepository->get($orderId);
        try {
            $this->orderAnalyse->updateShippingAddress([$order]);
            $msg =  __('Shipping address for order #%1 corrected', $order->getIncrementId());
            $this->messageManager->addSuccessMessage($msg);
        } catch (CouldNotSaveException|LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_redirect->getRefererUrl());

        return $resultRedirect;
    }
}
