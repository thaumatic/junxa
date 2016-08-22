<?php

namespace Thaumatic\Junxa\Query;

use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;
use Thaumatic\Junxa\Query\Element;

/**
 * Used internally by part of the fluent interface to
 * Thaumatic\Junxa\Query\Builder to provide contextualized interaction.
 */
final class Part
{

    private $query;
    private $part;

    /**
     * Static factory method.
     *
     * @param Thaumatic\Junxa\Query\Builder the query builder the generated
     * Part is to be attached to
     * @param string the name of the query part we are modeling
     * @return self
     */
    public static function make(QueryBuilder $query, $part)
    {
        return new self($query, $part);
    }

    /**
     * @param Thaumatic\Junxa\Query\Builder the query we are to be attached to
     * @param string the name of the query part we are modeling
     */
    public function __construct(QueryBuilder $query, $part)
    {
        $this->query = $query;
        $this->part = $part;
    }

    /**
     * Retrieves the parent query.
     *
     * @return Thaumatic\Junxa\Query\Builder
     */
    public function query()
    {
        return $this->query;
    }

    /**
     * Add a column assignment to the relevant part of the query.
     *
     * @param mixed the left-hand side of the assignment
     * @param mixed the right-hand side of the assignment
     * @return $this
     */
    public function set($a, $b)
    {
        switch ($this->part) {
            case 'insert':
            case 'update':
            case 'replace':
                $this->query->{$this->part}($a, $b);
                break;
            default:
                $this->query->{$this->part}(Q::set($a, $b));
                break;
        }
        return $this;
    }

    /**
     * Add an equivalency test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function equal($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::eq($a, $b));
        return $this;
    }

    /**
     * Add an equivalency test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function eq($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::eq($a, $b));
        return $this;
    }

    /**
     * Add an inequivalency test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function unequal($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::ne($a, $b));
        return $this;
    }

    /**
     * Add an inequivalency test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function ne($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::ne($a, $b));
        return $this;
    }

    /**
     * Add a greater-than test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function greater($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::gt($a, $b));
        return $this;
    }

    /**
     * Add a greater-than test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function gt($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::gt($a, $b));
        return $this;
    }

    /**
     * Add a less-than test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function less($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::lt($a, $b));
        return $this;
    }

    /**
     * Add a less-than test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function lt($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::lt($a, $b));
        return $this;
    }

    /**
     * Add a greater-than-or-equal test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function greaterOrEqual($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::ge($a, $b));
        return $this;
    }

    /**
     * Add a greater-than-or-equal test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function ge($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::ge($a, $b));
        return $this;
    }

    /**
     * Add a less-than-or-equal test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function lessOrEqual($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::le($a, $b));
        return $this;
    }

    /**
     * Add a less-than-or-equal test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function le($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::le($a, $b));
        return $this;
    }

    /**
     * Add a likeness test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function like($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::like($a, $b));
        return $this;
    }

    /**
     * Add a non-likeness test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function notLike($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::notLike($a, $b));
        return $this;
    }

    /**
     * Add a regular expression likeness test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function rlike($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::rlike($a, $b));
        return $this;
    }

    /**
     * Add a regular expression test to the relevant part of the query.
     *
     * @param mixed the left-hand side of the test
     * @param mixed the right-hand side of the test
     * @return $this
     */
    public function regexp($a, $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::regexp($a, $b));
        return $this;
    }

    /**
     * Add a truth test to the relevant part of the query.
     *
     * @param mixed what to test
     * @return $this
     */
    public function true($what)
    {
        if (is_string($what) && $this->query->table()) {
            $what = $this->query->table()->$what;
        }
        $this->query->{$this->part}(Q::eq($what, true));
        return $this;
    }

    /**
     * Add a falsehood test to the relevant part of the query.
     *
     * @param mixed what to test
     * @return $this
     */
    public function false($what)
    {
        if (is_string($what) && $this->query->table()) {
            $what = $this->query->table()->$what;
        }
        $this->query->{$this->part}(Q::eq($what, false));
        return $this;
    }

    /**
     * Add a membership test to the relevant part of the query.
     *
     * @param mixed what to test for membership
     * @param array what to test for membership in
     * @return $this
     */
    public function in($a, array $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::in($a, $b));
        return $this;
    }

    /**
     * Add a non-membership test to the relevant part of the query.
     *
     * @param mixed what to test for non-membership
     * @param array what to test for non-membership in
     * @return $this
     */
    public function notIn($a, array $b)
    {
        if (is_string($a) && $this->query->table()) {
            $a = $this->query->table()->$a;
        }
        $this->query->{$this->part}(Q::not_in($a, $b));
        return $this;
    }

    /**
     * Add a field to the relevant part of the query.
     *
     * @param mixed the field
     * @return $this
     */
    public function field($what)
    {
        $this->query->{$this->part}($what);
        return $this;
    }

    /**
     * Add an explicit value to the relevant part of the query.
     *
     * @param mixed the explicit value
     * @return $this
     */
    public function value($what)
    {
        $this->query->{$this->part}(Q::paren($what));
        return $this;
    }

    /**
     * Convert the most recently added order item to descending sort.
     *
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if $this
     * is not attached to the order part of the Query
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there
     * are no order items
     */
    public function desc()
    {
        if ($this->part !== 'order') {
            throw new JunxaInvalidQueryException('can only call desc() on order part');
        }
        $this->query->desc();
        return $this;
    }

    /**
     * Alias the most recently added select item.
     *
     * @param string the alias name to use
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if $this
     * is not attached to the select part of the Query
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException if there
     * are no select items
     */
    public function alias($name)
    {
        if ($this->part !== 'select') {
            throw new JunxaInvalidQueryException('can only call alias() on select part');
        }
        $this->query->alias($name);
        return $this;
    }

    /**
     * Add a SQL function call to the relevant part of the query.
     *
     * @param mixed the function name
     * @param mixed... the arguments for the function
     * @return $this
     */
    public function func()
    {
        $args = func_get_args();
        $name = array_shift($args);
        $elem = new Element('function', $name, $args);
        $this->query->{$this->part}($elem);
        return $this;
    }

    /**
     * Add an OR clause to the relevant part of the query.
     *
     * @param mixed... the elements of the OR clause
     * @return $this
     */
    public function orClause()
    {
        $args = func_get_args();
        $this->query->{$this->part}(Q::orClause($args));
        return $this;
    }

    /**
     * For any method call not supported by this object, forward the call back
     * to the parent (which will, in most circumstaces, return itself so that
     * it is the new fluent reference point).
     *
     * @param string the method name
     * @param array the method arguments
     * @return mixed
     */
    public function __call($name, $args)
    {
        return call_user_func_array([$this->query, $name], $args);
    }

}
