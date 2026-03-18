<?php

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Unit\Model;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\AnalysisResult;

class AnalysisResultTest extends TestCase
{
    private AnalysisResult $subject;

    #[\Override]
    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);
        $this->subject = $objectManager->getObject(AnalysisResult::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function stringGetterProvider(): array
    {
        return [
            'getFirstName' => ['getFirstName'],
            'getLastName' => ['getLastName'],
            'getCity' => ['getCity'],
            'getPostalCode' => ['getPostalCode'],
            'getStreet' => ['getStreet'],
            'getStreetNumber' => ['getStreetNumber'],
        ];
    }

    #[Test]
    #[DataProvider('stringGetterProvider')]
    public function gettersReturnEmptyStringWhenDataIsNull(string $method): void
    {
        // getData() returns null for unset fields — getter must not throw TypeError
        $result = $this->subject->$method();
        self::assertSame('', $result, "$method() should return empty string when underlying data is null");
    }
}
