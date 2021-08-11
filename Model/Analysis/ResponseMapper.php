<?php

namespace PostDirekt\Addressfactory\Model\Analysis;

use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterface;
use PostDirekt\Addressfactory\Api\Data\AnalysisResultInterfaceFactory;
use PostDirekt\Sdk\AddressfactoryDirect\Api\Data\RecordInterface;

class ResponseMapper
{
    public const PARCELSTATION = 'Packstation';
    public const POSTSTATION = 'Postfiliale';
    public const POSTFACH = 'Postfach';
    public const BULKRECEIVER = 'Großempfänger';

    /**
     * @var AnalysisResultInterfaceFactory
     */
    private $analysisResultFactory;

    /**
     * @param AnalysisResultInterfaceFactory $analysisResultFactory
     */
    public function __construct(AnalysisResultInterfaceFactory $analysisResultFactory)
    {
        $this->analysisResultFactory = $analysisResultFactory;
    }

    /**
     * @param RecordInterface[] $records
     * @return AnalysisResultInterface[]
     */
    public function mapRecordsResponse(array $records): array
    {
        $newAnalysisResults = [];
        foreach ($records as $record) {
            $data = $this->mapAddressTypes($record);

            $data[AnalysisResultInterface::FIRST_NAME] = $record->getPerson() ?
                $record->getPerson()->getFirstName() : '';
            $data[AnalysisResultInterface::LAST_NAME] = $record->getPerson() ? $record->getPerson()->getLastName() : '';
            $data[AnalysisResultInterface::ORDER_ADDRESS_ID] = $record->getRecordId();
            $data[AnalysisResultInterface::STATUS_CODE] = implode(',', $record->getStatusCodes());
            $newAnalysisResult = $this->analysisResultFactory->create(['data' => $data]);
            $newAnalysisResults[$newAnalysisResult->getOrderAddressId()] = $newAnalysisResult;
        }

        return $newAnalysisResults;
    }

    /**
     * @return string[]
     */
    private function mapAddressTypes(RecordInterface $record): array
    {
        $data = [];
        if ($record->getAddress()) {
            $data[AnalysisResultInterface::POSTAL_CODE] = $record->getAddress()->getPostalCode();
            $data[AnalysisResultInterface::CITY] = $record->getAddress()->getCity();
            $data[AnalysisResultInterface::STREET] = $record->getAddress()->getStreetName();
            $data[AnalysisResultInterface::STREET_NUMBER] = trim(
                implode(' ', [
                    $record->getAddress()->getStreetNumber(),
                    $record->getAddress()->getStreetNumberAddition(),
                ])
            );
        }

        if ($record->getParcelStation()) {
            $data[AnalysisResultInterface::POSTAL_CODE] = $record->getParcelStation()->getPostalCode();
            $data[AnalysisResultInterface::CITY] = $record->getParcelStation()->getCity();
            $data[AnalysisResultInterface::STREET] = self::PARCELSTATION;
            $data[AnalysisResultInterface::STREET_NUMBER] = $record->getParcelStation()->getNumber();
        }

        if ($record->getPostOffice()) {
            $data[AnalysisResultInterface::POSTAL_CODE] = $record->getPostOffice()->getPostalCode();
            $data[AnalysisResultInterface::CITY] = $record->getPostOffice()->getCity();
            $data[AnalysisResultInterface::STREET] = self::POSTSTATION;
            $data[AnalysisResultInterface::STREET_NUMBER] = $record->getPostOffice()->getNumber();
        }

        if ($record->getPostalBox()) {
            $data[AnalysisResultInterface::POSTAL_CODE] = $record->getPostalBox()->getPostalCode();
            $data[AnalysisResultInterface::CITY] = $record->getPostalBox()->getCity();
            $data[AnalysisResultInterface::STREET] = self::POSTFACH;
            $data[AnalysisResultInterface::STREET_NUMBER] = $record->getPostalBox()->getNumber();
        }

        if ($record->getBulkReceiver()) {
            $data[AnalysisResultInterface::POSTAL_CODE] = $record->getBulkReceiver()->getPostalCode();
            $data[AnalysisResultInterface::CITY] = $record->getBulkReceiver()->getCity();
            $data[AnalysisResultInterface::STREET] = $record->getBulkReceiver()->getName();
            $data[AnalysisResultInterface::STREET_NUMBER] = '';
        }

        return $data;
    }

}
