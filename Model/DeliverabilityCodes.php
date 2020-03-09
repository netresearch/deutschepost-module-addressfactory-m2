<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

/**
 * Class DeliverabilityScore
 *
 * @author   Sebastian Ertner <sebastian.ertner@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class DeliverabilityCodes
{
    public const DELIVERABLE = 'deliverable';
    public const UNDELIVERABLE = 'undeliverable';
    public const POSSIBLY_DELIVERABLE = 'possibly_deliverable';

    private const PERSON_DELIVERABLE = 'PDC050105';
    private const PERSON_NOT_DELIVERABLE = 'PDC050106';
    private const HOUSEHOLD_DELIVERABLE = 'PDC040105';
    private const HOUSEHOLD_UNDELIVERABLE = 'PDC040106';
    private const BUILDING_DELIVERABLE = 'PDC030105';
    private const PERSON_NOT_MATCHED = 'PDC050500';
    private const HOUSEHOLD_NOT_MATCHED = 'PDC040500';
    private const BUILDING_UNDELIVERABLE = 'PDC030106';

    /**
     * @param string[] $codes
     * @return string
     */
    public function computeScore(array $codes): string
    {
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

        return self::POSSIBLY_DELIVERABLE;
    }

    /**
     * @param string[] $codes
     * @return string[]
     */
    public function getLabels(array $codes): array
    {
        $result = [];
        $mappedCodes = [
            self::PERSON_DELIVERABLE => __('Person deliverable'),
            self::PERSON_NOT_DELIVERABLE => __('Person not deliverable'),
            self::HOUSEHOLD_DELIVERABLE => __('Household deliverable'),
            self::HOUSEHOLD_UNDELIVERABLE => __('Household undeliverable'),
            self::BUILDING_DELIVERABLE => __('Building deliverable'),
            self::PERSON_NOT_MATCHED => __('Person not matched'),
            self::HOUSEHOLD_NOT_MATCHED => __('Household not matched'),
            self::BUILDING_UNDELIVERABLE => __('Building undeliverable'),
        ];

        foreach ($codes as $code) {
            if (isset($mappedCodes[$code])) {
                $result[] = $mappedCodes[$code];
            }
        }

        return $result;
    }
}
