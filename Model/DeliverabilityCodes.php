<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

class DeliverabilityCodes
{
    public const DELIVERABLE = 'deliverable';
    public const UNDELIVERABLE = 'undeliverable';
    public const POSSIBLY_DELIVERABLE = 'possibly_deliverable';
    public const CORRECTION_REQUIRED = 'correction_required';

    private const PERSON_DELIVERABLE = 'PDC050105';
    private const PERSON_NOT_DELIVERABLE = 'PDC050106';
    private const HOUSEHOLD_DELIVERABLE = 'PDC040105';
    private const HOUSEHOLD_UNDELIVERABLE = 'PDC040106';
    private const BUILDING_DELIVERABLE = 'PDC030105';
    private const PERSON_NOT_MATCHED = 'PDC050500';
    private const HOUSEHOLD_NOT_MATCHED = 'PDC040500';
    private const BUILDING_UNDELIVERABLE = 'PDC030106';
    private const NOT_CORRECTABLE = 'BAC000111';
    private const HOUSE_NUMBER_NOT_FILLED = 'FNC030501';

    private const STATUS_CODES_SIGNIFICANTLY_CORRECTED = ['103', '108'];

    /**
     * @param string[] $codes
     * @param bool $wasAlreadyUpdated
     * @return string
     */
    public function computeScore(array $codes, bool $wasAlreadyUpdated = false): string
    {
        $codes = $this->filterInapplicable($codes);

        if (!$wasAlreadyUpdated) {
            foreach ($codes as $code) {
                $statusCode = substr($code, -3, 3);
                if (in_array($statusCode, self::STATUS_CODES_SIGNIFICANTLY_CORRECTED, true)) {
                    return self::CORRECTION_REQUIRED;
                }
            }
        }

        if (\in_array(self::HOUSE_NUMBER_NOT_FILLED, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        if (\in_array(self::NOT_CORRECTABLE, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        if (\in_array(self::PERSON_DELIVERABLE, $codes, true)) {
            return self::DELIVERABLE;
        }

        if (\in_array(self::PERSON_NOT_DELIVERABLE, $codes, true) &&
            \in_array(self::HOUSEHOLD_DELIVERABLE, $codes, true)) {
            return self::DELIVERABLE;
        }

        if (\in_array(self::PERSON_NOT_DELIVERABLE, $codes, true) &&
            \in_array(self::HOUSEHOLD_UNDELIVERABLE, $codes, true) &&
            !\in_array(self::BUILDING_DELIVERABLE, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        if (\in_array(self::PERSON_NOT_MATCHED, $codes, true) &&
            \in_array(self::HOUSEHOLD_DELIVERABLE, $codes, true)) {
            return self::DELIVERABLE;
        }

        if (\in_array(self::PERSON_NOT_MATCHED, $codes, true) &&
            \in_array(self::HOUSEHOLD_UNDELIVERABLE, $codes, true) &&
            \in_array(self::BUILDING_DELIVERABLE, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        if (\in_array(self::PERSON_NOT_MATCHED, $codes, true) &&
            \in_array(self::HOUSEHOLD_NOT_MATCHED, $codes, true) &&
            \in_array(self::BUILDING_DELIVERABLE, $codes, true)) {
            return self::POSSIBLY_DELIVERABLE;
        }

        if (\in_array(self::PERSON_NOT_MATCHED, $codes, true) &&
            \in_array(self::HOUSEHOLD_NOT_MATCHED, $codes, true) &&
            \in_array(self::BUILDING_UNDELIVERABLE, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        if (\in_array(self::PERSON_NOT_DELIVERABLE, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        return self::POSSIBLY_DELIVERABLE;
    }

    /**
     * @param string[] $codes
     * @return string[][]
     */
    public function getLabels(array $codes): array
    {
        $mappedCodes = [
            self::NOT_CORRECTABLE => [
                'icon' => 'icon-alert',
                'label' => __('Address not valid'),
                'code' => self::NOT_CORRECTABLE,
            ],
            'FNC000500' => [
                'icon' => 'icon-alert',
                'label' => __('Not found in reference'),
                'code' => 'FNC000500',
            ]
        ];

        $mappedModuleCodes = [
            'BAC' => '',
            'FNC' => '',
        ];

        $mappedFieldCodes = [
            '000' => '',
            '010' => __('House address'),
            '012' => __('Bulk recipient address'),
            '020' => __('Street'),
            '030' => __('House number'), // "Building"
            '040' => __('Household'),
            '050' => __('Person'),
            '060' => __('Postal code'),
            '100' => __('Postal code'),
            '101' => __('City'),
            '102' => __('Street'),
            '103' => __('City addition'),
            '105' => __('City'),
            '106' => __('Street'),
            '110' => __('Postal code'),
            '111' => __('City'),
            '113' => __('City addition'),
            '115' => __('City'),
            '120' => __('Postal code'),
            '121' => __('City'),
            '122' => __('Bulk receiver name'),
            '123' => __('City addition'),
            '125' => __('City'),
            '130' => __('Postal code'),
            '131' => __('City'),
            '133' => __('City addition'),
            '135' => __('City'),
            '144' => __('Country'),
            '170' => __('Postal code'),
            '171' => __('City'),
            '173' => __('City addition'),
//            '011' => __('Post box address'),
//            '013' => __('Parcel station address'),
//            '017' => __('Post office address'),
//            '104' => __('District'),
//            '112' => __('Post box number'),
//            '132' => __('Post box number'),
//            '140' => __('Political information'),
//            '145' => __('Route code'),
//            '150' => __('Political information'),
//            '154' => __('Route code'),
//            '160' => __('Political information'),
//            '164' => __('Route code'),
//            '172' => __('Post office number'),
//            '200' => __('House number'),
//            '201' => __('House number addition'),
        ];

        $mappedStatusCodes = [
            '103' => __('corrected significantly'),
            '104' => __('corrected marginally'),
            '106' => __('undeliverable'),
            '108' => __('incorporated or renamed'),
            '111' => __('different'),
            '112' => __('moved'),
            '113' => __('address type changed'),
            '120' => __('receiver deceased'),
            '121' => __('reportedly deceased'),
            '140' => __('matched in Robinson list'),
            '141' => __('matched in fake-name list'),
            '500' => __('not found'), // "not matched"
            '501' => __('not filled'),
            '503' => __('ambiguous'),
            '504' => __('is foreign address'),
            '505' => __('incorporated'),
            '506' => __('is company address'),
//            '107' => __('enriched'),
//            '105' => __('deliverable'),
//            '509' => __('not queried'),
//            '102' => __('correct'),
//            '110' => __('separated from original data'),
//            '130' => __('doublet'),
//            '131' => __('head doublet'),
//            '132' => __('followed doublet'),
//            '135' => __('followed doublet in negative list'),
        ];

        $labels = [];
        // remove redundant codes
        $codes = $this->filterInapplicable($codes);

        foreach ($codes as $code) {
            if (isset($mappedCodes[$code])) {
                $labels[] = $mappedCodes[$code];
                continue;
            }

            $moduleCode = substr($code, 0, 3);
            $fieldCode = substr($code, -6, 3);
            $statusCode = substr($code, -3, 3);

            if (isset($mappedModuleCodes[$moduleCode], $mappedFieldCodes[$fieldCode], $mappedStatusCodes[$statusCode])) {
                $iconCode = $this->mapToIcon($fieldCode);
                $label = ucfirst(trim(
                    $mappedModuleCodes[$moduleCode] . ' '
                     . $mappedFieldCodes[$fieldCode] . ' '
                     . $mappedStatusCodes[$statusCode]
                ));
                $labels[] = [
                    'icon' => $iconCode,
                    'label' => $label,
                    'code' => $code,
                ];
            }
        }

        return $labels;
    }

    /**
     * @param string[] $codes
     * @return string[]
     */
    private function filterInapplicable(array $codes): array
    {
        /**
         * BAC201110 - House numbers can be separated by the API, but Magento cannot take advantage of this
         * BAC010103, BAC010104, BAC010500 - These are always explained in more detail by another code.
         * FNC201103 - Street number addition corrected: This is a false positive in connection with BAC201110
         */
        $inapplicable = ['BAC201110', 'BAC010103', 'BAC010500', 'BAC010104', 'FNC201103'];
        $codes = array_diff($codes, $inapplicable);
        if (\in_array(self::NOT_CORRECTABLE, $codes, true)) {
            /**
             * If BAC000111 (not correctable) is set, all other analysis modules become irrelevant
             */
            $codes = array_filter($codes, static fn($key) => str_contains((string) $key, 'BAC'));
        }

        return $codes;
    }

    private function mapToIcon(string $fieldCode): string
    {
        $inHouse = ['010','012', '030'];

        switch ($fieldCode) {
            case '000':
                $iconCode = 'icon-alert';
                break;
            case in_array($fieldCode, $inHouse, true):
                $iconCode = 'icon-house';
                break;
            case '050':
                $iconCode = 'icon-user-account';
                break;
            case '040':
                $iconCode = 'icon-user-group';
                break;
            default:
                $iconCode = 'icon-info';
        }
        return $iconCode;
    }
}
