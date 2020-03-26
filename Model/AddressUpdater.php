<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;

class AddressUpdater
{
    /**
     * @var OrderAddressRepositoryInterface
     */
    private $orderAddressRepository;

    public function __construct(OrderAddressRepositoryInterface $orderAddressRepository)
    {
        $this->orderAddressRepository = $orderAddressRepository;
    }

    /**
     * Overwrite the given Order Address with data from an ADDRESSFACTORY DIRECT deliverability analyis.
     *
     * The only address fields that will be modified are:
     * - first name
     * - last name
     * - street
     * - city
     * - postal code
     *
     * Note: Do not use this method directly when operating on Order scope,
     * use PostDirekt\Addressfactory\Model\OrderAnalysis::updateShippingAddress instead
     * to keep the Order's analysis status in sync.
     *
     * @param AnalysisResult $analysisResult
     * @param OrderAddressInterface|null $address
     * @return bool If the Address update was successfull
     */
    public function update(AnalysisResult $analysisResult, ?OrderAddressInterface $address = null): bool
    {
        if ($address === null) {
            try {
                $address = $this->orderAddressRepository->get($analysisResult->getOrderAddressId());
            } catch (NoSuchEntityException $exception) {
                // The repository interface is missing the annotation,
                // but its default implementation can throw NoSuchEntityException.
                return false;
            }
        }
        $street = implode(' ', [$analysisResult->getStreet(), $analysisResult->getStreetNumber()]);
        $address->setStreet($street);
        $address->setFirstname($analysisResult->getFirstName());
        $address->setLastname($analysisResult->getLastName());
        $address->setPostcode($analysisResult->getPostalCode());
        $address->setCity($analysisResult->getCity());

        try {
            $this->orderAddressRepository->save($address);
        } catch (CouldNotSaveException $exception) {
            // The repository interface is missing the annotation,
            // but its default implementation can throw CouldNotSaveException.
            return false;
        }

        return true;
    }
}
