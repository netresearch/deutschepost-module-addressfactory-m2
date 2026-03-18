<?php

declare(strict_types=1);

namespace PostDirekt\Addressfactory\Test\Unit\Model\Config\Source;

use Monolog\Level;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PostDirekt\Addressfactory\Model\Config\Source\LogLevel;

class LogLevelTest extends TestCase
{
    #[Test]
    public function optionArrayContainsCorrectMonologLevelValues(): void
    {
        $subject = new LogLevel();
        $options = $subject->toOptionArray();

        self::assertCount(2, $options);
        self::assertSame((string) Level::Info->value, $options[0]['value']);
        self::assertSame((string) Level::Error->value, $options[1]['value']);
    }
}
