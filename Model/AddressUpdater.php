<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Exception\CouldNotSaveException;
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
     * @param OrderAddressInterface $address
     * @param AnalysisResult $analysisResult
     * @throws CouldNotSaveException    The repository interface is missing this annotation,
     *                                  but its default implementation can throw it.
     */
    public function update(OrderAddressInterface $address, AnalysisResult $analysisResult): void
    {
        $street = implode(' ', [$analysisResult->getStreet(), $analysisResult->getStreetNumber()]);
        $address->setStreet($street);
        $address->setFirstname($analysisResult->getFirstName());
        $address->setLastname($analysisResult->getLastName());
        $address->setPostcode($analysisResult->getPostalCode());
        $address->setCity($analysisResult->getCity());

        $this->orderAddressRepository->save($address);
    }
}
