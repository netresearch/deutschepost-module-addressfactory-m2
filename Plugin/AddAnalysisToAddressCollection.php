<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Plugin;

use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\ResourceModel\Order\Address\Collection;

class AddAnalysisToAddressCollection
{
    /**
     * @var JoinProcessorInterface
     */
    protected $extensionAttributesJoinProcessor;

    public function __construct(JoinProcessorInterface $extensionAttributesJoinProcessor)
    {
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
    }

    public function beforeLoadWithFilter(Collection $addressCollection)
    {
        $this->extensionAttributesJoinProcessor->process($addressCollection, Address::class);
        return null;
    }
}
