<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Model\Analysis\ResponseMapper;
use PostDirekt\Core\Model\Config as CoreConfig;
use PostDirekt\Sdk\AddressfactoryDirect\Api\ServiceFactoryInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Exception\AuthenticationException;
use PostDirekt\Sdk\AddressfactoryDirect\Exception\ServiceException;
use PostDirekt\Sdk\AddressfactoryDirect\Model\RequestType\InRecordWSType;
use PostDirekt\Sdk\AddressfactoryDirect\RequestBuilder\RequestBuilder;
use Psr\Log\LoggerInterface;

class AddressAnalysis
{
    /**
     * @var AnalysisResultRepository
     */
    private $analysisResultRepository;

    /**
     * @var ServiceFactoryInterface
     */
    private $serviceFactory;

    /**
     * @var RequestBuilder
     */
    private $requestBuilder;

    /**
     * @var CoreConfig
     */
    private $coreConfig;

    /**
     * @var Config
     */
    private $moduleConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ResponseMapper
     */
    private $recordResponseMapper;

    public function __construct(
        AnalysisResultRepository $analysisResultRepository,
        ServiceFactoryInterface $serviceFactory,
        RequestBuilder $requestBuilder,
        CoreConfig $coreConfig,
        Config $moduleConfig,
        LoggerInterface $logger,
        ResponseMapper $recordResponseMapper
    ) {
        $this->analysisResultRepository = $analysisResultRepository;
        $this->serviceFactory = $serviceFactory;
        $this->requestBuilder = $requestBuilder;
        $this->coreConfig = $coreConfig;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
        $this->recordResponseMapper = $recordResponseMapper;
    }

    /**
     * @param OrderAddressInterface[] $addresses
     * @return AnalysisResultInterface[]
     * @throws LocalizedException
     */
    public function analyse(array $addresses): array
    {
        $addressIds = [];
        foreach ($addresses as $address) {
            $addressIds[] = $address->getEntityId();
        }

        $analysisResults = $this->analysisResultRepository->getListByAddressIds($addressIds);
        /** @var InRecordWSType[] $recordRequests */
        $recordRequests = array_reduce(
            $addresses,
            function (array $recordRequests, OrderAddressInterface $orderAddress) use ($analysisResults) {
                if (!array_key_exists($orderAddress->getEntityId(), $analysisResults)) {
                    $recordRequests[] = $this->buildRequest($orderAddress);
                }

                return $recordRequests;
            },
            []
        );

        if (empty($recordRequests)) {
            return $analysisResults;
        }

        try {
            $service = $this->serviceFactory->createAddressVerificationService(
                $this->coreConfig->getApiUser(),
                $this->coreConfig->getApiPassword(),
                $this->logger
            );
            $records = $service->getRecords(
                $recordRequests,
                null,
                $this->moduleConfig->getConfigurationName(),
                $this->moduleConfig->getMandateName()
            );
            $newAnalysisResults = $this->recordResponseMapper->mapRecordsResponse($records);
            $this->analysisResultRepository->saveList($newAnalysisResults);
        } catch (AuthenticationException $exception) {
            throw new LocalizedException(__('Authentication error.', $exception->getMessage()), $exception);
        } catch (ServiceException $exception) {
            throw new LocalizedException(__('Service exception: %1', $exception->getMessage()), $exception);
        } catch (CouldNotSaveException $exception) {
            throw new LocalizedException(__('Could not save analysis result.'), $exception);
        }

        // add new records to previously analysis results from db, do a union on purpose to keep keys
        return $newAnalysisResults + $analysisResults;
    }

    private function buildRequest(OrderAddressInterface $address): InRecordWSType
    {
        $this->requestBuilder->setMetadata((int) $address->getEntityId());
        $this->requestBuilder->setAddress(
            $address->getCountryId(),
            $address->getPostcode(),
            $address->getCity(),
            implode(' ', $address->getStreet()),
            ''
        );
        $this->requestBuilder->setPerson(
            $address->getFirstname(),
            $address->getLastname()
        );

        return $this->requestBuilder->create();
    }
}
