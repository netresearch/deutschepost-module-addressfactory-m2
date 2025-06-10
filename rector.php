<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/Api',
        __DIR__ . '/Component',
        __DIR__ . '/Controller',
        __DIR__ . '/Cron',
        __DIR__ . '/Model',
        __DIR__ . '/Observer',
        __DIR__ . '/Plugin',
        __DIR__ . '/Test',
        __DIR__ . '/view',
        __DIR__ . '/ViewModel',
    ])
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withSets([
        SetList::PHP_81,
        SetList::PHP_82,
        SetList::PHP_83,
        SetList::PHP_84,
        PHPUnitSetList::PHPUNIT_100
    ])
    ->withPHPStanConfigs(phpstanConfigs: [__DIR__ . '/phpstan.neon'])
    ->withSkip([
        // Skip specific rules if needed
        ReadOnlyPropertyRector::class,
        ReadOnlyClassRector::class,
        AddTypeToConstRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
    ]);
