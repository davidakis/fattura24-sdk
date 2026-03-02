<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@PHP81Migration' => true,
        
        // Arrays
        'array_syntax' => ['syntax' => 'short'],
        'no_whitespace_before_comma_in_array' => true,
        'whitespace_after_comma_in_array' => true,
        'trim_array_spaces' => true,
        
        // Control structures
        'no_alternative_syntax' => true,
        'simplified_if_return' => true,
        'yoda_style' => false,
        
        // Imports
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
        
        // Whitespace
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'try', 'throw'],
        ],
        'method_chaining_indentation' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],
        'no_spaces_around_offset' => true,
        'no_trailing_whitespace' => true,
        'no_whitespace_in_blank_line' => true,
        
        // Casting
        'cast_spaces' => true,
        'modernize_types_casting' => true,
        'no_unset_cast' => true,
        
        // PHPDoc
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_indent' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_empty_return' => true,
        'phpdoc_no_package' => true,
        'phpdoc_scalar' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_trim' => true,
        'phpdoc_types' => true,
        'phpdoc_var_without_name' => true,
        
        // Functions
        'function_typehint_space' => true,
        'return_type_declaration' => true,
        'void_return' => true,
        
        // String
        'single_quote' => true,
        
        // Strict
        'strict_comparison' => true,
        'strict_param' => true,
        
        // Other
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => false, // Don't force strict_types
        'native_function_invocation' => [
            'include' => ['@all'],
            'scope' => 'namespaced',
        ],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'ternary_to_null_coalescing' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache');
