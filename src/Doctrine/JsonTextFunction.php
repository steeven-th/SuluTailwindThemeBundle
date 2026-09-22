<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Reads one top-level key of a JSON column as text, on every database Sulu runs on.
 *
 * DQL has no way to look inside a JSON column, and Sulu stores the fields of a
 * template there: what an editor typed into `startDate` lives in
 * `templateData`, not in a column of its own. Filtering or sorting on such a
 * field therefore needs the database's own JSON reader, and every engine
 * spells it differently.
 *
 *     IW_JSON_TEXT(dimensionContent.templateData, 'startDate')
 *
 * Reading as TEXT rather than as a date is deliberate. Sulu writes a datetime
 * as `Y-m-d\TH:i:s` ({@see \Sulu\Content\Application\PropertyResolver\Resolver\DateTimePropertyResolver::FORMAT}),
 * a format whose lexicographic order is its chronological order, so comparing
 * and sorting works on strings and no engine has to agree on how to cast one.
 *
 * The key is a literal, checked at parse time, because it is written into the
 * SQL rather than bound: SQL Server and Oracle only accept a constant JSON
 * path, so there is no form of this function where the key travels as a
 * parameter on every engine.
 */
final class JsonTextFunction extends FunctionNode
{
    /**
     * The shape a key may have: what a Sulu template property can be named.
     *
     * Anything else is refused rather than escaped - a key is written by a
     * developer into a query, never by a visitor, so a rejected one is a typo
     * to fix and not an input to sanitise.
     */
    private const KEY_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public Node|string $jsonExpression;

    public string $key = '';

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->jsonExpression = $parser->StringPrimary();

        $parser->match(TokenType::T_COMMA);
        $parser->match(TokenType::T_STRING);

        $key = $parser->getLexer()->token?->value ?? '';
        \assert(\is_string($key));

        if (1 !== \preg_match(self::KEY_PATTERN, $key)) {
            throw QueryException::semanticalError(
                \sprintf('IW_JSON_TEXT() expects a plain property name as its second argument, got "%s".', $key),
            );
        }

        $this->key = $key;

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $column = $sqlWalker->walkStringPrimary($this->jsonExpression);
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();
        $path = "'$." . $this->key . "'";

        return match (true) {
            // Postgres reads jsonb natively. The key travels as a quoted
            // literal here too, for one spelling of the function rather than
            // two ways of passing the same thing.
            $platform instanceof PostgreSQLPlatform => \sprintf("(%s ->> '%s')", $column, $this->key),

            // MariaDB and every MySQL version extend the same abstract
            // platform, and both read a JSON path the same way.
            $platform instanceof AbstractMySQLPlatform => \sprintf('JSON_UNQUOTE(JSON_EXTRACT(%s, %s))', $column, $path),

            $platform instanceof SQLitePlatform => \sprintf('JSON_EXTRACT(%s, %s)', $column, $path),

            $platform instanceof SQLServerPlatform,
            $platform instanceof OraclePlatform,
            $platform instanceof DB2Platform => \sprintf('JSON_VALUE(%s, %s)', $column, $path),

            default => throw QueryException::semanticalError(\sprintf(
                'IW_JSON_TEXT() has no JSON reader for "%s". Reading a template field from the database '
                . 'needs one, so either add it here or keep to a platform this bundle covers.',
                $platform::class,
            )),
        };
    }
}
