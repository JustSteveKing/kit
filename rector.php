<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Renaming\Rector\ClassConstFetch\RenameClassConstFetchRector;
use Rector\Renaming\Rector\PropertyFetch\RenamePropertyRector;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;
use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;
use RectorLaravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

/*
 * Rector changes code, never formatting: Pint (pint.json, the PER preset) owns style, so the
 * codingStyle set is left out. Run Rector first, then Pint;
 * `composer rector:check` is what CI runs, and passes once both have been applied.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap/app.php',
        __DIR__ . '/bootstrap/providers.php',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    // Imports names as the rest of Kit does; Pint then orders the imports.
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        earlyReturn: true,
    )
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130_WITHOUT_ATTRIBUTES,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
    ])
    ->withSkip([
        // Renames a property fetch called "events" to Eloquent's old $dispatchesEvents, which breaks
        // a model attribute of that name, such as WebhookSubscription::$events.
        RenamePropertyRector::class,
        // Laravel's own skeleton keeps PDO::MYSQL_ATTR_SSL_CA for PHP before 8.5 on purpose.
        RenameClassConstFetchRector::class => [__DIR__ . '/config/database.php'],
        // Kit's conventions: Job::dispatch(), app() and Carbon, not the helpers or the Date facade.
        DispatchToHelperFunctionsRector::class,
        AppToResolveRector::class,
        CarbonToDateFacadeRector::class,
    ]);
