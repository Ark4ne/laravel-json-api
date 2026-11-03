<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin', __DIR__ . '/config'])
    ->append([__FILE__]);

$config = new PhpCsFixer\Config();

// The CI runs the fixer on PHP 8.4; allow newer local runtimes without the deprecated env variable.
if (method_exists($config, 'setUnsupportedPhpVersionAllowed')) {
    $config->setUnsupportedPhpVersionAllowed(true);
}

return $config
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/build/.php-cs-fixer.cache')
    ->setRules([
        '@PER-CS' => true,
        '@PER-CS:risky' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
        'native_function_invocation' => false,
        'strict_comparison' => true,
        'strict_param' => true,
        'void_return' => true,
        'phpdoc_trim' => true,
        'no_empty_phpdoc' => true,
    ])
    ->setFinder($finder);
