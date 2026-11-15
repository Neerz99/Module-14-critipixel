<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'vendor', 'public', 'migrations'])
    ->notPath(['config/bundles.php', 'config/reference.php'])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'declare_strict_types' => true,
        'global_namespace_import' => ['import_classes' => true, 'import_constants' => false, 'import_functions' => false],
        'native_function_invocation' => false,
        'phpdoc_to_comment' => false,
        'yoda_style' => false,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
