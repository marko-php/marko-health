<?php

declare(strict_types=1);

it('creates valid package scaffolding with composer.json, module.php, and config', function () {
    $packageRoot = dirname(__DIR__);

    expect(file_exists($packageRoot . '/composer.json'))->toBeTrue()
        ->and(json_decode(file_get_contents($packageRoot . '/composer.json'), true)['name'])->toBe('marko/health')
        ->and(file_exists($packageRoot . '/module.php'))->toBeTrue()
        ->and(file_exists($packageRoot . '/config/health.php'))->toBeTrue();

    $config = require $packageRoot . '/config/health.php';

    // The route path is fixed by the #[Get('/health')] attribute, so there is no
    // 'path' key; 'secret' is the only setting.
    expect(array_keys($config))->toBe(['secret']);

    $module = require $packageRoot . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings');
});
