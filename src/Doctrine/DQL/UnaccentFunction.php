<?php

/**
 * Función DQL UNACCENT para PostgreSQL: permite búsquedas insensibles a acentos.
 */

namespace App\Doctrine\DQL;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Función DQL para PostgreSQL unaccent().
 * Requiere la extensión unaccent habilitada en la base de datos.
 *
 * Uso en DQL: UNACCENT(campo)
 * Genera SQL: unaccent(campo)
 */
class UnaccentFunction extends FunctionNode
{
    private $stringExpression;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->stringExpression = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        return 'unaccent('.$this->stringExpression->dispatch($sqlWalker).')';
    }
}
