<?php

namespace Thaumatic\Junxa;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Query\Assignment;
use Thaumatic\Junxa\Query\Element;

/**
 * This class essentially functions as a switchboard for generating
 * query element models, as an alternative or supplement to the
 * fluent interface methods supported by the query builder.  All
 * methods are static factory methods.
 */
class Query
{

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
        if($arg instanceof Column && $arg->type === 'tinyint' && $arg->length === 1)
            return new Element('comparison', '=', [$arg, 0]);
        else
            return new Element('unary', 'NOT', $arg);
    }

    public static function paren()
    {
        $args = func_get_args();
        return new Element('interleave', '', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
    }

    public static function andClause()
    {
        $args = func_get_args();
        return new Element('interleave', 'AND', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
    }

    public static function orClause()
    {
        $args = func_get_args();
        return new Element('interleave', 'OR', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
    }

    public static function xorClause()
    {
        $args = func_get_args();
        return new Element('interleave', 'XOR', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
    }

    public static function add()
    {
        $args = func_get_args();
        return new Element('interleave', '+', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
    }

    public static function subtract()
    {
        $args = func_get_args();
        return new Element('interleave', '-', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
    }

    public static function multiply()
    {
        $args = func_get_args();
        return new Element('interleave', '*', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
    }

    public static function divide()
    {
        $args = func_get_args();
        return new Element('interleave', '/', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
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

    public static function cast($what, $type) {
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

    public static function joinUsing()
    {
        $args = func_get_args();
        return new Element('joincond', 'USING', count($args) == 1 && is_array($args[0]) ? $args[0] : $args);
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
