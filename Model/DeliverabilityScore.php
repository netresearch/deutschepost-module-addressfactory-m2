<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

/**
 * Class DeliverabilityScore
 *
 * @author   Gurjit Singh <gurjit.singh@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class DeliverabilityScore
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
    private const HOUSEHOLD_NOT_MACTHED = 'PDC040500';
    private const BUILDING_UNDELIVERABLE = 'PDC030106';

    /**
     * @param mixed $codes
     * @return string
     */
    public function compute(array $codes): string
    {
        if (in_array(self::PERSON_DELIVERABLE, $codes, true)) {
            return self::DELIVERABLE;
        }

        if (in_array(self::PERSON_NOT_DELIVERABLE, $codes, true) &&
            in_array(self::HOUSEHOLD_DELIVERABLE, $codes, true)) {
            return self::DELIVERABLE;
        }

        if (in_array(self::PERSON_NOT_DELIVERABLE, $codes, true) &&
            in_array(self::HOUSEHOLD_UNDELIVERABLE, $codes, true) &&
            !in_array(self::BUILDING_DELIVERABLE, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        if (in_array(self::PERSON_NOT_MATCHED, $codes, true) &&
            in_array(self::HOUSEHOLD_DELIVERABLE, $codes, true)) {
            return self::DELIVERABLE;
        }

        if (in_array(self::PERSON_NOT_MATCHED, $codes, true) &&
            in_array(self::HOUSEHOLD_UNDELIVERABLE, $codes, true) &&
            in_array(self::BUILDING_DELIVERABLE, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        if (in_array(self::PERSON_NOT_MATCHED, $codes, true) &&
            in_array(self::HOUSEHOLD_NOT_MACTHED, $codes, true) &&
            in_array(self::BUILDING_DELIVERABLE, $codes, true)) {
            return self::POSSIBLY_DELIVERABLE;
        }

        if (in_array(self::PERSON_NOT_MATCHED, $codes, true) &&
            in_array(self::HOUSEHOLD_NOT_MACTHED, $codes, true) &&
            in_array(self::BUILDING_UNDELIVERABLE, $codes, true)) {
            return self::UNDELIVERABLE;
        }

        return self::POSSIBLY_DELIVERABLE;
    }
}
