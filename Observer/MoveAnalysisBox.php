<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Block\Adminhtml\Order\View\Info;

/**
 * Observer to take our custom block 'addressfactory_analysis_data' and copies it's rendered output to the end of the
 * parent block.
 *
 * It also sets the 'shipping_address_id' value for the block. This has the side effect that the original
 * 'addressfactory_analysis_data' block in the original position has no output.
 */
class MoveAnalysisBox implements ObserverInterface
{
    /**
     * @var RequestInterface|Http
     */
    private $request;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    /**
     * @param Observer $observer
     * @throws LocalizedException
     * @event view_block_abstract_to_html_after
     */
    public function execute(Observer $observer): void
    {
        if ($this->request->getFullActionName() !== 'sales_order_view') {
            // not the order details page
            return;
        }

        $block = $observer->getData('block');
        if (!$block instanceof Info) {
            return;
        }

        $order = $block->getOrder();
        $shippingAddress = $order->getShippingAddress();
        if (!$order instanceof OrderInterface || !$shippingAddress || $shippingAddress->getCountryId() !== 'DE') {
            // wrong type, virtual or corrupt order
            return;
        }

        $analysisResultBlock = $block->getChildBlock('addressfactory_analysis_data');
        if (!$analysisResultBlock instanceof AbstractBlock) {
            return;
        }

        $analysisResultBlock->setData('template_should_display', true);

        $transport = $observer->getData('transport');
        $html = $transport->getData('html');
        $html .= $analysisResultBlock->toHtml();
        $transport->setData('html', $html);
    }
}
