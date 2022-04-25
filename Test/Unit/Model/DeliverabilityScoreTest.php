<?php

/**
 * See LICENSE.md for license details.
 */

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\DeliverabilityCodes;

class DeliverabilityScoreTest extends TestCase
{
    public function testCompute(): void
    {
        $subject = new DeliverabilityCodes();

        self::assertSame(
            $subject::DELIVERABLE,
            $subject->computeScore(['PDC050105']),
            'Deliverable person must result in "Deliverable" score.'
        );
        self::assertSame(
            $subject::DELIVERABLE,
            $subject->computeScore(['PDC050106', 'PDC040105']),
            'Undeliverable person but deliverable household must result in "Deliverable" score.'
        );
        self::assertSame(
            $subject::UNDELIVERABLE,
            $subject->computeScore(['PDC050106', 'PDC040106']), // Note: 'PDC030105' missing
            'Undeliverable person and household, and no deliverable building must result in "Undeliverable" score.'
        );
        self::assertSame(
            $subject::DELIVERABLE,
            $subject->computeScore(['PDC050500', 'PDC040105']),
            'Unmatched person but deliverable household must result in "Deliverable" score.'
        );
        self::assertSame(
            $subject::POSSIBLY_DELIVERABLE,
            $subject->computeScore(['PDC050106', 'PDC040106', 'PDC030105']), // Note: 'PDC030105' present
            'Undeliverable person and household, but deliverable building must result in "Possibly deliverable" score.'
        );
        self::assertSame(
            $subject::UNDELIVERABLE,
            $subject->computeScore(['PDC050500', 'PDC040106', 'PDC030105']),
            'Unmatched person and household, but deliverable building must result in "Undeliverable" score.'
        );
        self::assertSame(
            $subject::POSSIBLY_DELIVERABLE,
            $subject->computeScore(['PDC050500', 'PDC040500', 'PDC030105']),
            'Unmatched person and household, but deliverable building must result in "Possibly deliverable" score.'
        );
        self::assertSame(
            $subject::UNDELIVERABLE,
            $subject->computeScore(['PDC050500', 'PDC040500', 'PDC030106']),
            'Unmatched person and household and undeliverable building must result in "Undeliverable" score.'
        );
        self::assertSame(
            $subject::POSSIBLY_DELIVERABLE,
            $subject->computeScore(['PDC050106', 'PDC030105']),
            'Undeliverable person but deliverable building must result in "Possibly deliverable" score (not decideable without household info).'
        );
        self::assertSame(
            $subject::POSSIBLY_DELIVERABLE,
            $subject->computeScore([]),
            'The default score must be "Possibly deliverable".'
        );
    }
}
