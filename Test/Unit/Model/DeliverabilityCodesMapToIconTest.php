<?php

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Unit\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\DeliverabilityCodes;

class DeliverabilityCodesMapToIconTest extends TestCase
{
    /**
     * @return array<string, array{string[], string, string}>
     */
    public static function iconMappingProvider(): array
    {
        // getLabels returns entries with 'icon' key. We use codes that map
        // to known fieldCodes to verify the icon mapping.
        return [
            'house address field maps to icon-house' => [
                ['BAC010106'],
                'BAC010106',
                'icon-house',
            ],
            'bulk recipient maps to icon-house' => [
                ['BAC012103'],
                'BAC012103',
                'icon-house',
            ],
            'building/house number maps to icon-house' => [
                ['FNC030501'],
                'FNC030501',
                'icon-house',
            ],
            'person maps to icon-user-account' => [
                ['BAC050106'],
                'BAC050106',
                'icon-user-account',
            ],
            'household maps to icon-user-group' => [
                ['BAC040106'],
                'BAC040106',
                'icon-user-group',
            ],
            'general alert maps to icon-alert' => [
                ['BAC000106'],
                'BAC000106',
                'icon-alert',
            ],
            'other field maps to icon-info' => [
                ['FNC060503'],
                'FNC060503',
                'icon-info',
            ],
        ];
    }

    #[Test]
    #[DataProvider('iconMappingProvider')]
    public function iconMappingIsCorrect(array $codes, string $expectedCode, string $expectedIcon): void
    {
        $subject = new DeliverabilityCodes();
        $labels = $subject->getLabels($codes);

        $found = false;
        foreach ($labels as $label) {
            if ($label['code'] === $expectedCode) {
                self::assertSame($expectedIcon, $label['icon'], "Code $expectedCode should map to icon $expectedIcon");
                $found = true;
                break;
            }
        }

        self::assertTrue($found, "Expected code $expectedCode not found in labels output");
    }
}
