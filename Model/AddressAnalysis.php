<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderAddressInterface;
use PostDirekt\Core\Model\Config as CoreConfig;
use PostDirekt\Sdk\AddressfactoryDirect\Api\Data\RecordInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Api\ServiceFactoryInterface;
use PostDirekt\Sdk\AddressfactoryDirect\Exception\AuthenticationException;
use PostDirekt\Sdk\AddressfactoryDirect\Exception\ServiceException;
use PostDirekt\Sdk\AddressfactoryDirect\Model\RequestType\InRecordWSType;
use PostDirekt\Sdk\AddressfactoryDirect\RequestBuilder\RequestBuilder;
use Psr\Log\LoggerInterface;

/**
 * AddressAnalysis
 *
 * @author  Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link    https://www.netresearch.de/
 */
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
     * @var AnalysisResultFactory
     */
    private $analysisResultFactory;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    public function __construct(
        AnalysisResultRepository $analysisResultRepository,
        ServiceFactoryInterface $serviceFactory,
        RequestBuilder $requestBuilder,
        CoreConfig $coreConfig,
        Config $moduleConfig,
        LoggerInterface $logger,
        AnalysisResultFactory $analysisResultFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->analysisResultRepository = $analysisResultRepository;
        $this->serviceFactory = $serviceFactory;
        $this->requestBuilder = $requestBuilder;
        $this->coreConfig = $coreConfig;
        $this->moduleConfig = $moduleConfig;
        $this->logger = $logger;
        $this->analysisResultFactory = $analysisResultFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param OrderAddressInterface[] $addresses
     * @return AnalysisResult[]
     * @throws LocalizedException
     */
    public function analyse(array $addresses): array
    {
        $addressIds = [];
        foreach ($addresses as $address) {
            $addressIds[] = $address->getEntityId();
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(AnalysisResult::ORDER_ADDRESS_ID, $addressIds, 'in')
            ->create();

        $analysisResults = $this->analysisResultRepository->getList($searchCriteria)->getItems();
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
                $this->logger,
                $this->moduleConfig->isSandboxMode()
            );
            $records = $service->getRecords($recordRequests, null, $this->moduleConfig->getConfigurationName());
            $newAnalysisResults = $this->mapRecordsResponse($records);
            $this->analysisResultRepository->saveList($newAnalysisResults);
        } catch (AuthenticationException $exception) {
            throw new LocalizedException(__('Authentication error.', $exception->getMessage()), $exception);
        } catch (ServiceException $exception) {
            throw new LocalizedException(__('Service exception: %1', $exception->getMessage()), $exception);
        } catch (CouldNotSaveException $exception) {
            throw new LocalizedException(__('Could not save analysis result.'), $exception);
        }

        // add new records to previously analysis results from db, do a union on purpose to keep keys
        $analysisResults = $newAnalysisResults + $analysisResults;

        return $analysisResults;
    }

    /**
     * @param RecordInterface[] $records
     * @return AnalysisResult[]
     */
    private function mapRecordsResponse(array $records): array
    {
        $newAnalysisResults = [];
        foreach ($records as $record) {
            $newAnalysisResult = $this->analysisResultFactory->create();
            if ($record->getAddress()) {
                $newAnalysisResult->setPostalCode($record->getAddress()->getPostalCode());
                $newAnalysisResult->setCity($record->getAddress()->getCity());
                $newAnalysisResult->setStreet($record->getAddress()->getStreetName());
                $newAnalysisResult->setStreetNumber(trim(implode(' ', [
                    $record->getAddress()->getStreetNumber(),
                    $record->getAddress()->getStreetNumberAddition()
                ])));
            }
            $newAnalysisResult->setFirstName($record->getPerson() ? $record->getPerson()->getFirstName() : '');
            $newAnalysisResult->setLastName($record->getPerson() ? $record->getPerson()->getLastName() : '');
            $newAnalysisResult->setOrderAddressId($record->getRecordId());
            $newAnalysisResult->setStatusCodes($record->getStatusCodes());
            $newAnalysisResults[$newAnalysisResult->getOrderAddressId()] = $newAnalysisResult;
        }

        return $newAnalysisResults;
    }

    private function buildRequest(OrderAddressInterface $address): InRecordWSType
    {
        $this->requestBuilder->setMetadata((int)$address->getEntityId());
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
