<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/modules', __DIR__ . '/themes', __DIR__ . '/tests'])
    ->append([__FILE__, __DIR__ . '/spark', __DIR__ . '/public/index.php'])
    ->exclude(['Views'])
    ->name('*.php');

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setCacheFile(__DIR__ . '/build/.php-cs-fixer.cache')
    ->setRules([
        '@PSR12'                     => true,
        'declare_strict_types'       => true,
        'array_syntax'               => ['syntax' => 'short'],
        'no_unused_imports'          => true,
        'ordered_imports'            => ['sort_algorithm' => 'alpha', 'imports_order' => ['class', 'function', 'const']],
        'single_quote'               => true,
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
    ])
    ->setFinder($finder);
