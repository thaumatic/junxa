<?php

namespace Thaumatic\Junxa\Events;

use Symfony\Component\EventDispatcher\Event;
use Thaumatic\Junxa;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;

/**
 * Event class for Junxa queries.
 */
class JunxaQueryEvent extends Event
{

    const NAME = 'junxa.query';

    /**
     * @var Thaumatic\Junxa the database model the event is taking place on
     */
    private $database;

    /**
     * @var string the final rendered SQL for the query
     */
    private $sql;

    /**
     * @var Thaumatic\Junxa\Query\Builder the query builder the query was
     * rendered from, if any
     */
    private $queryBuilder;

    /**
     * @var bool whether we have been requested to prevent the query
     */
    private $preventQuery;

    /**
     * @param Thaumatic\Junxa the database model the event is taking place on
     * @param string the final rendered SQL for the query
     * @param Thaumatic\Junxa\Query\Builder|null the query builder the query
     * was rendered from, if any
     */
    final public function __construct(
        Junxa $database,
        $sql,
        QueryBuilder $queryBuilder = null
    ) {
        $this->database = $database;
        $this->sql = $sql;
        $this->queryBuilder = $queryBuilder;
        $this->init();
    }

    /**
     * Initialization function to be called upon the event model being set
     * up.  Intended to be overridden by child classes.
     */
    protected function init()
    {
    }

    /**
     * @return Thaumatic\Junxa the database model the event is taking place on
     */
    final public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return string the final rendered SQL for the query
     */
    final public function getSql()
    {
        return $this->sql;
    }

    /*
     * @return Thaumatic\Junxa\Query\Builder the query builder the query was
     * rendered from, if any
     */
    final public function getQueryBuilder()
    {
        return $this->queryBuilder;
    }

    /**
     * Sets whether to request that the query for which the event was generated
     * should be prevented from executing.
     *
     * @param bool whether to prevent the query from executing
     */
    final public function setPreventQuery($flag)
    {
        $this->preventQuery = $flag;
    }

    /**
     * @return bool whether to prevent the query from executing
     */
    final public function getPreventQuery()
    {
        return $this->preventQuery;
    }

    
}
