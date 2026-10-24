<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['var', 'config', 'public'])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PhpCsFixer' => true,
        '@auto' => true,
        'yoda_style' => false,
        // @coversNothing on every test class would leave the coverage report empty.
        'php_unit_test_class_requires_covers' => false,
    ])
    ->setFinder($finder)
;
