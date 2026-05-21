<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// Documentación oficial: https://github.com/FriendsOfPHP/PHP-CS-Fixer
// Documentación set de reglas Symfony: https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/ruleSets/Symfony.rst

$finder = Finder::create()
    ->in([__DIR__.'/src'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(false) // Evita reglas que puedan romper lógica
    ->setUsingCache(true) // Habilita el uso de caché para mejorar el rendimiento en ejecuciones posteriores
    ->setRules([
        // REGLAS BASE
        '@Symfony' => true,

        // Estilos
        'method_chaining_indentation' => true, // Indenta las llamadas encadenadas.
        'multiline_whitespace_before_semicolons' => false, // Evita que se generen espacios o saltos de línea justo antes de un punto y coma en declaraciones largas o multilínea.
        'yoda_style' => false,

        // Importaciones
        'no_leading_import_slash' => true, // Evita el slash inicial opcional en imports

        // Comentarios y líneas en blanco
        'no_trailing_whitespace_in_comment' => true,
        'no_whitespace_in_blank_line' => true,
        'multiline_comment_opening_closing' => true,
    ])

    ->setFinder($finder) // Define qué archivos se analizarán (el finder configurado arriba)
    ->setCacheFile(__DIR__.'/.php-cs-fixer.cache'); // Define la ruta del archivo de caché donde se almacenan los resultados previos
