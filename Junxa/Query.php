<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Query\Assignment;
use Thaumatic\Junxa\Query\Element;
use Thaumatic\Junxa\Row;

/**
 * This class essentially functions as a switchboard for generating
 * query element models, as an alternative or supplement to the
 * fluent interface methods supported by the query builder.  All
 * public methods are static factory methods.
 */
final class Query
{

    private static function processArgs(array $args)
    {
        return count($args) === 1 && is_array($args[0]) ? $args[0] : $args;
    }

    public static function set(Column $column, $value)
    {
        return new Assignment($column, $value);
    }

    public static function eq($a, $b)
    {
        return new Element('comparison', '=', [$a, $b]);
    }

    public static function ne($a, $b)
    {
        return new Element('comparison', '!=', [$a, $b]);
    }

    public static function xeq($a, $b)
    {
        return new Element('comparison', '===', [$a, $b]);
    }

    public static function xne($a, $b)
    {
        return new Element('comparison', '!==', [$a, $b]);
    }

    public static function gt($a, $b)
    {
        return new Element('comparison', '>', [$a, $b]);
    }

    public static function ge($a, $b)
    {
        return new Element('comparison', '>=', [$a, $b]);
    }

    public static function lt($a, $b)
    {
        return new Element('comparison', '<', [$a, $b]);
    }

    public static function le($a, $b)
    {
        return new Element('comparison', '<=', [$a, $b]);
    }

    public static function like($a, $b)
    {
        return new Element('comparison', 'LIKE', [$a, $b]);
    }

    public static function regexp($a, $b)
    {
        return new Element('comparison', 'REGEXP', [$a, $b]);
    }

    public static function rlike($a, $b)
    {
        return new Element('comparison', 'RLIKE', [$a, $b]);
    }

    public static function in($a, $b)
    {
        return new Element('container', 'IN', [$a, $b]);
    }

    public static function notIn($a, $b)
    {
        return new Element('container', 'NOT IN', [$a, $b]);
    }

    public static function not($arg)
    {
        if ($arg instanceof Column && $arg->getTypeClass() === 'int') {
            return new Element('comparison', '=', [$arg, 0]);
        } else {
            return new Element('unary', 'NOT', $arg);
        }
    }

    public static function explicitNot($arg)
    {
        return new Element('unary', 'NOT', $arg);
    }

    public static function paren(...$args)
    {
        return new Element('interleave', '', self::processArgs($args));
    }

    public static function andClause(...$args)
    {
        return new Element('interleave', 'AND', self::processArgs($args));
    }

    public static function orClause(...$args)
    {
        return new Element('interleave', 'OR', self::processArgs($args));
    }

    public static function xorClause(...$args)
    {
        return new Element('interleave', 'XOR', self::processArgs($args));
    }

    public static function add(...$args)
    {
        return new Element('interleave', '+', self::processArgs($args));
    }

    public static function subtract(...$args)
    {
        return new Element('interleave', '-', self::processArgs($args));
    }

    public static function multiply(...$args)
    {
        return new Element('interleave', '*', self::processArgs($args));
    }

    public static function divide(...$args)
    {
        return new Element('interleave', '/', self::processArgs($args));
    }

    public static function distinct($arg)
    {
        return new Element('head', 'DISTINCT', $arg);
    }

    public static function asc($arg)
    {
        return new Element('tail', 'ASC', $arg);
    }

    public static function desc($arg)
    {
        return new Element('tail', 'DESC', $arg);
    }

    public static function func()
    {
        $args = func_get_args();
        $name = array_shift($args);
        return new Element('function', $name, $args);
    }

    public static function cast($what, $type)
    {
        return new Element('cast', $type, $what);
    }

    public static function literal($arg)
    {
        return new Element('literal', $arg);
    }

    public static function alias($what, $name)
    {
        return new Element('alias', 'AS', [$what, $name]);
    }

    public static function interval($num, $type)
    {
        return new Element('interval', $type, $num);
    }

    public static function joinOn($cond)
    {
        return new Element('joincond', 'ON', is_array($cond) ? self::andClause($cond) : $cond);
    }

    public static function joinUsing(...$args)
    {
        return new Element('joincond', 'USING', self::processArgs($args));
    }

    public static function crossJoin($table)
    {
        return new Element('join', 'CROSS', $table);
    }

    public static function straightJoin($table)
    {
        return new Element('join', 'STRAIGHT', $table);
    }

    public static function innerJoin($table)
    {
        return new Element('join', '', $table);
    }

    public static function naturalLeftJoin($table)
    {
        return new Element('join', 'NATURAL LEFT', $table);
    }

    public static function naturalRightJoin($table)
    {
        return new Element('join', 'NATURAL RIGHT', $table);
    }

    public static function leftJoin($table)
    {
        return new Element('join', 'LEFT', $table);
    }

    public static function rightJoin($table)
    {
        return new Element('join', 'RIGHT', $table);
    }

}
