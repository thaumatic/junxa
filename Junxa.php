<?php

namespace Thaumatic;

use ICanBoogie\Inflector;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Events\JunxaQueryEvent;
use Thaumatic\Junxa\Exceptions\JunxaConfigurationException;
use Thaumatic\Junxa\Exceptions\JunxaInvalidIdentifierException;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException;
use Thaumatic\Junxa\Exceptions\JunxaQueryExecutionException;
use Thaumatic\Junxa\Query as Q;
use Thaumatic\Junxa\Query\Builder as QueryBuilder;
use Thaumatic\Junxa\Table;

/**
 * This class is used to model the database that we are connecting to
 * and provides a central point of interface for the ORM.
 *
 *      use Thaumatic\Junxa;
 *
 *      $db = new Junxa([
 *          'hostname'  => 'db.example.com',
 *          'database'  => 'userdb',
 *          'username'  => 'admin',
 *          'password'  => 'somepassword',
 *      ]);
 *      $table = $db->user;
 *      $column = $table->first_name;
 *
 *      $db = new Junxa
 *          ->setDatabase('mydb')
 *          ->username('root')
 *          ->ready();
 *      $table = $db->myTable;
 *      $column = $table->myField;
 */
class Junxa
{

    /**
     * @const int database-level behavioral option: cache row results from
     * tables
     */
    const DB_CACHE_TABLE_ROWS           = 0x00000001;

    /**
     * @const int database-level behavioral option: collect statistics on
     * queries executed
     */
    const DB_COLLECT_QUERY_STATISTICS   = 0x00000002;

    /**
     * @const int database-level behavioral option: use a persistent connection
     */
    const DB_PERSISTENT_CONNECTION      = 0x00000004;

    /**
     * @const int database-level behavioral option: interpret tables names as
     * plurals
     */
    const DB_TABLES_ARE_PLURALS         = 0x00000008;

    /**
     * @const int query output type: raw result from database interface module
     */
    const QUERY_RAW                     = 1;

    /**
     * @const int query output type: discard any results, return null
     */
    const QUERY_FORGET                  = 2;

    /**
     * @const int query output type: return results in associative arrays
     */
    const QUERY_ASSOCS                  = 3;

    /**
     * @const int query output type: return results in numerically-indexed
     * arrays
     */
    const QUERY_ARRAYS                  = 4;

    /**
     * @const int query output type: return results in associative and
     * numerically-indexed arrays
     */
    const QUERY_DUAL_ARRAYS             = 5;

    /**
     * @const int query output type: return results in stdClass objects
     */
    const QUERY_OBJECTS                 = 6;

    /**
     * @const int query output type: return results in a single associative
     * array
     */
    const QUERY_SINGLE_ASSOC            = 7;

    /**
     * @const int query output type: return results in a single
     * numerically-indexed array
     */
    const QUERY_SINGLE_ARRAY            = 8;

    /**
     * @const int query output type: return results in a single stdClass object
     */
    const QUERY_SINGLE_OBJECT           = 9;

    /**
     * @const int query output type: return results in a single scalar value
     */
    const QUERY_SINGLE_CELL             = 10;

    /**
     * @const int query output type: return results in an associative array
     * mapping results of a two-column query
     */
    const QUERY_COLUMN_ASSOC            = 11;

    /**
     * @const int query output type: return results in a numerically-indexed
     * array containing results of a single-column query
     */
    const QUERY_COLUMN_ARRAY            = 12;

    /**
     * @const int query output type: return results in a stdClass object
     * mapping results of a two-column query
     */
    const QUERY_COLUMN_OBJECT           = 13;

    /**
     * @const int query option: INSERT and REPLACE clauses generated from this
     * query should use the DELAYED modifier
     */
    const OPTION_DELAYED                    = 0x00000001;

    /**
     * @const int query option: a SELECT clause generated from this query
     * should use the DISTINCT modifier
     */
    const OPTION_DISTINCT                   = 0x00000002;

    /**
     * @const int query option: single element result sets should result in a
     * null return value rather than raising an exception if they find no
     * results, and don't return {@see Thaumatic\Junxa::RESULT_INSERT_FAIL},
     * {@see Thaumatic\Junxa::RESULT_UPDATE_FAIL}, or
     * {@see Thaumatic\Junxa::RESULT_DELETE_FAIL} on their respective
     * conditions
     */
    const OPTION_EMPTY_OKAY                 = 0x00000004;

    /**
     * @const int query option: don't raise exceptions on query failures
     */
    const OPTION_ERROR_OKAY                 = 0x00000008;

    /**
     * @const int query option: force this query to be sent to the database's
     * change handler, if any
     */
    const OPTION_FORCE_USE_CHANGE_HANDLER   = 0x00000010;

    /**
     * @const int query option: INSERT and REPLACE clauses used in this query
     * should use the HIGH_PRIORITY modifier (overrides OPTION_LOW_PRIORITY
     * and OPTION_DELAYED)
     */
    const OPTION_HIGH_PRIORITY              = 0x00000020;

    /**
     * @const int query option: INSERT and REPLACE clauses used in this query
     * should use the IGNORE modifier
     */
    const OPTION_IGNORE                     = 0x00000040;

    /**
     * @const int query option: INSERT and REPLACE clauses used in this query
     * should use the LOW_PRIORITY modifier (overrides OPTION_DELAYED)
     */
    const OPTION_LOW_PRIORITY               = 0x00000080;

    /**
     * @const int query option: don't raise an exception on a delete() of a row
     * that has already been deleted
     */
    const OPTION_REDELETE_OKAY              = 0x00000100;

    /**
     * @const int query option: don't cache rows retrieved with this query
     * (only meaningful if {@see DB_CACHE_TABLE_ROWS} is enabled at the
     * database level)
     */
    const OPTION_SUPPRESS_CACHING           = 0x00000200;

    /**
     * @const int query option: echo query for debugging
     */
    const OPTION_DEBUG_ECHO                 = 0x00000400;

    /**
     * @const int query result code: absolutely everything went perfectly with
     * the query
     */
    const RESULT_SUCCESS                = 1;

    /**
     * @const int query result code: a table row could not be refreshed with its
     * current content from the database
     *
     * A table row refresh is automatically called for after an table row
     * insert.  The refresh will fail if the table does not have a primary key or
     * if the primary key is not auto_increment and does not have a value in the
     * table row object (applies to any part of a multipart primary key).
     */
    const RESULT_REFRESH_FAIL           = 2;

    /**
     * @const int query result code: a table row update was called for but no
     * changes had been made to the table row's data
     */
    const RESULT_UPDATE_NOOP            = 3;

    /**
     * @const int query result code: a table row find was called for and more
     * than one matching row was found, resulting in the first row being used.
     */
    const RESULT_FIND_EXCESS            = 4;

    /**
     * @const int query result code: the query was requested to be prevented
     * by an event listener
     */
    const RESULT_PREVENTED              = 5;

    /**
     * @const int query result code: the database reports an error
     */
    const RESULT_FAILURE                = -1;

    /**
     * @const int query result code: a table row insert was called for but the
     * table row had no values set
     */
    const RESULT_INSERT_NOOP            = -2;

    /**
     * @const int query result code: a table row replace was called for but the
     * table row had no values set
     */
    const RESULT_REPLACE_NOOP           = -3;

    /**
     * @const int query result code: a table row merge was called for but no
     * changes had been made to the table row's data
     */
    const RESULT_MERGE_NOOP             = -4;

    /**
     * @const int query result code: a table row update was called for but the
     * row did not have the primary key information necessary to automatically
     * generate an update
     */
    const RESULT_UPDATE_NOKEY           = -5;

    /**
     * @const int query result code: a table row delete was called for but the
     * row did not have the primary key information necessary to automatically
     * generate a delete
     */
    const RESULT_DELETE_NOKEY           = -6;

    /**
     * @const int query result code: a table row find was called for and no
     * matching rows were found
     */
    const RESULT_FIND_FAIL              = -7;

    /**
     * @const int query result code: an INSERT IGNORE query was executed and no
     * rows were affected
     */
    const RESULT_INSERT_FAIL            = -8;

    /**
     * @const int query result code: an UPDATE query affected no rows
     */
    const RESULT_UPDATE_FAIL            = -9;

    /**
     * @const int query result code: a DELETE query affected no rows
     */
    const RESULT_DELETE_FAIL            = -10;

    /**
     * @const array<numeric:string> mapping of the RESULT_* constants to their
     * string names
     */
    const RESULT_NAMES                  = [
        self::RESULT_SUCCESS            => 'RESULT_SUCCESS',
        self::RESULT_REFRESH_FAIL       => 'RESULT_REFRESH_FAIL',
        self::RESULT_UPDATE_NOOP        => 'RESULT_UPDATE_NOOP',
        self::RESULT_FIND_EXCESS        => 'RESULT_FIND_EXCESS',
        self::RESULT_PREVENTED          => 'RESULT_PREVENTED',
        self::RESULT_FAILURE            => 'RESULT_FAILURE',
        self::RESULT_INSERT_NOOP        => 'RESULT_INSERT_NOOP',
        self::RESULT_REPLACE_NOOP       => 'RESULT_REPLACE_NOOP',
        self::RESULT_MERGE_NOOP         => 'RESULT_MERGE_NOOP',
        self::RESULT_UPDATE_NOKEY       => 'RESULT_UPDATE_NOKEY',
        self::RESULT_DELETE_FAIL        => 'RESULT_DELETE_FAIL',
        self::RESULT_FIND_FAIL          => 'RESULT_FIND_FAIL',
        self::RESULT_INSERT_FAIL        => 'RESULT_INSERT_FAIL',
        self::RESULT_UPDATE_FAIL        => 'RESULT_UPDATE_FAIL',
    ];

    /**
     * @const array<string> list of PHP keywords, used for identifier validation
     */
    const PHP_KEYWORDS = [
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'jcho',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        '__halt_compiler',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
    ];

    /**
     * @var string the hostname to connect to MySQL on
     */
    private $hostname;

    /**
     * @var int the port to connect to MySQL on
     */
    private $port;

    /**
     * @var string the socket or named pipe to connect to MySQL on, if applicable
     */
    private $socket;

    /**
     * @var string the name of the MySQL database to connect to
     */
    private $databaseName;

    /**
     * @var string the username to use to connect to the database
     */
    private $username;

    /**
     * @var string the username to use to connect to the database
     */
    private $password;

    /**
     * @var array<string:mixed>|Thaumatic\Junxa an alternate Junxa
     * configuration or instance that database changes (not selects)
     * should be sent to (for primary/secondary replication architectures)
     */
    private $changeHandler;

    /**
     * @var Thaumatic\Junxa the object to send changes to, either the same as
     * $changeHandler or built based on it
     */
    private $changeHandlerObject;

    /**
     * @var int bitmask of Thaumatic\Junxa::DB_* values for Junxa's general
     * behavior
     */
    private $options = 0;

    /**
     * @var string a namespace that can be expected to be populated with
     * column model classes that Junxa should use
     */
    private $autoColumnClassNamespace;

    /**
     * @var string a namespace that can be expected to be populated with row
     * model classes that Junxa should use
     */
    private $autoRowClassNamespace;

    /**
     * @var string a namespace that can be expected to be populated with table
     * model classes that Junxa should use
     */
    private $autoTableClassNamespace;

    /**
     * @var array<string:string> map of table names to the columns that, in
     * combination with an autoRowClassNamespace, class names for individual
     * rows can be sourced from
     */
    private $individualRowClassColumns = [];

    /**
     * @var array<string:string> map of classes to use as models for columns
     * by name
     */
    private $columnClasses = [];

    /**
     * @var array<string:string> map of classes to use as models for tables
     * by name
     */
    private $tableClasses = [];

    /**
     * @var array<string:string> map of classes to use as models for rows by
     * name
     */
    private $rowClasses = [];

    /**
     * @var array<string:string> map of classes to use as models for columns
     * by regexp pattern
     */
    private $regexpColumnClasses = [];

    /**
     * @var array<string:string> map of classes to use as models for rows by
     * regexp pattern
     */
    private $regexpRowClasses = [];

    /**
     * @var array<string:string> map of for classes to use as models
     * for tables by regexp pattern
     */
    private $regexpTableClasses = [];

    /**
     * @var string the name of the default class to use for column models if
     * one is not otherwise found
     */
    private $defaultColumnClass;

    /**
     * @var string the name of the default class to use for row models if
     * one is not otherwise found
     */
    private $defaultRowClass;

    /**
     * @var string the name of the default class to use for table models if
     * one is not otherwise found
     */
    private $defaultTableClass;

    /**
     * @var string interpret this suffix as conventionally used in the database
     * to designate a foreign key column; for example, if this is 'Id', then
     * the column 'itemId' will be interpreted as a foreign key into the table
     * 'item'
     */
    private $foreignKeySuffix;

    /**
     * @var string an internally used regular expression for matching column
     * names based on the foreign key suffix
     */
    private $foreignKeySuffixPattern;

    /**
     * @var array<string:string> mapping of plural nouns to singular, used to
     * allow specified overrides of the default inflection behavior
     */
    private $pluralToSingularMap = [];

    /**
     * @var array<string:string> mapping of singular nouns to plural, used to
     * allow specified overrides of the default inflection behavior
     */
    private $singularToPluralMap = [];

    /**
     * @var string locale to use for grammatical inflection (see
     * {@link https://github.com/ICanBoogie/Inflector} for supported locales)
     */
    private $inflectionLocale = Inflector::DEFAULT_LOCALE;

    /**
     * @var ICanBoogie\Inflector grammatical inflector interface object
     */
    private $inflector;

    /**
     * @var mysql the mysqli connection object
     */
    private $link;

    /**
     * @var array<string> the names of the tables in the database
     */
    private $tables;

    /**
     * @var array<string:Thaumatic\Junxa\Table> the map of loaded table models
     */
    private $tableModels = [];

    /**
     * @var numeric the last insert ID from the database
     */
    private $insertId;

    /**
     * @var string the last mysqli error result on a query
     */
    private $queryMessage;

    /**
     * @var int Thaumatic\Junxa::RESULT_* value for the last query
     */
    private $queryStatus;

    /**
     * @var array<string:int> per-instance statistical array of how many
     * times a given query is run
     */
    private $queryStatistics = [];

    /**
     * @var Symfony\Component\EventDispatcher\EventDispatcher the database
     * model's demand-loaded event dispatcher
     */
    private $eventDispatcher;

    /**
     * @var array<string:int> class-general statistical array of how many
     * times a given query is run
     */
    private static $overallQueryStatistics = [];

    /**
     * Static factory method.
     *
     * @param array<string:mixed> array of configuration parameters; see set
     * functions for each for details
     */
    final public static function make(array $def = null)
    {
        return new self($def);
    }

    /**
     * Constructor.
     *
     * @param array<string:mixed> array of configuration parameters; see set
     * functions for each for details
     */
    public function __construct(array $def = null)
    {
        if ($def !== null) {
            if (array_key_exists('hostname', $def)) {
                $this->setHostname($def['hostname']);
                unset($def['hostname']);
            }
            if (array_key_exists('port', $def)) {
                $this->setPort($def['port']);
                unset($def['port']);
            }
            if (array_key_exists('socket', $def)) {
                $this->setPort($def['socket']);
                unset($def['socket']);
            }
            if (array_key_exists('databaseName', $def)) {
                $this->setDatabaseName($def['databaseName']);
                unset($def['databaseName']);
            }
            if (array_key_exists('username', $def)) {
                $this->setUsername($def['username']);
                unset($def['username']);
            }
            if (array_key_exists('password', $def)) {
                $this->setPassword($def['password']);
                unset($def['password']);
            }
            if (array_key_exists('options', $def)) {
                $this->setOptions($def['options']);
                unset($def['options']);
            }
            if (array_key_exists('defaultTableClass', $def)) {
                $this->setDefaultTableClass($def['defaultTableClass']);
                unset($def['defaultTableClass']);
            }
            if (array_key_exists('defaultColumnClass', $def)) {
                $this->setDefaultColumnClass($def['defaultColumnClass']);
                unset($def['defaultColumnClass']);
            }
            if (array_key_exists('defaultRowClass', $def)) {
                $this->setDefaultRowClass($def['defaultRowClass']);
                unset($def['defaultRowClass']);
            }
            if (array_key_exists('autoTableClassNamespace', $def)) {
                $this->setAutoTableClassNamespace($def['autoTableClassNamespace']);
                unset($def['autoTableClassNamespace']);
            }
            if (array_key_exists('autoColumnClassNamespace', $def)) {
                $this->setAutoColumnClassNamespace($def['autoColumnClassNamespace']);
                unset($def['autoColumnClassNamespace']);
            }
            if (array_key_exists('autoRowClassNamespace', $def)) {
                $this->setAutoRowClassNamespace($def['autoRowClassNamespace']);
                unset($def['autoRowClassNamespace']);
            }
            if (array_key_exists('individualRowClassColumns', $def)) {
                $this->setIndividualRowClassColumns($def['individualRowClassColumns']);
                unset($def['individualRowClassColumns']);
            }
            if (array_key_exists('regexpTableClasses', $def)) {
                $this->setRegexpTableClasses($def['regexpTableClasses']);
                unset($def['regexpTableClasses']);
            }
            if (array_key_exists('regexpColumnClasses', $def)) {
                $this->setRegexpColumnClasses($def['regexpColumnClasses']);
                unset($def['regexpColumnClasses']);
            }
            if (array_key_exists('regexpRowClasses', $def)) {
                $this->setRegexpRowClasses($def['regexpRowClasses']);
                unset($def['regexpRowClasses']);
            }
            if (array_key_exists('tableClasses', $def)) {
                $this->setTableClasses($def['tableClasses']);
                unset($def['tableClasses']);
            }
            if (array_key_exists('columnClasses', $def)) {
                $this->setColumnClasses($def['columnClasses']);
                unset($def['columnClasses']);
            }
            if (array_key_exists('rowClasses', $def)) {
                $this->setRowClasses($def['rowClasses']);
                unset($def['rowClasses']);
            }
            if (array_key_exists('foreignKeySuffix', $def)) {
                $this->setForeignKeySuffix($def['foreignKeySuffix']);
                unset($def['foreignKeySuffix']);
            }
            if (array_key_exists('pluralToSingularMap', $def)) {
                $this->setPluralToSingularMap($def['pluralToSingularMap']);
                unset($def['pluralToSingularMap']);
            }
            if (array_key_exists('singularToPluralMap', $def)) {
                $this->setSingularToPluralMap($def['singularToPluralMap']);
                unset($def['singularToPluralMap']);
            }
            if (array_key_exists('inflectionLocale', $def)) {
                $this->setInflectionLocale($def['inflectionLocale']);
                unset($def['inflectionLocale']);
            }
            if (array_key_exists('changeHandler', $def)) {
                $this->setChangeHandler($def['changeHandler']);
                unset($def['changeHandler']);
            }
            if ($def) {
                throw new JunxaConfigurationException(
                    'unsupported configuration '
                    . (count($def) === 1 ? 'setting' : 'settings')
                    . ': '
                    . join(', ', array_keys($def))
                );
            }
            $this->ready();
        }
    }

    /**
     * To be called when the database model is fully configured.  Called
     * automatically from the constructor when configuring via an array
     * specification; needs to be called explicitly when configuring in
     * fluent mode.
     *
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException if the
     * database model's configuration is invalid
     * @return $this
     */
    final public function ready()
    {
        $this->connect();
        $this->determineTables();
        $this->init();
        return $this;
    }

    /**
     * Initialization function to be called upon the database model being set
     * up.  Intended to be overridden by child classes.
     */
    protected function init()
    {
    }

    /**
     * Sets the hostname to connect to MySQL on.
     *
     * @param string hostname
     * @return $this
     */
    final public function setHostname($val)
    {
        $this->hostname = $val;
        return $this;
    }

    /**
     * Retrieves the hostname we connect to MySQL on.
     *
     * @return string
     */
    final public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * Sets the port to connect to MySQL on.
     *
     * @param int port number
     * @return $this
     */
    final public function setPort($val)
    {
        $this->port = $val;
        return $this;
    }

    /**
     * Retrieves the port we connect to MySQL on.
     *
     * @return int
     */
    final public function getPort()
    {
        return $this->port;
    }

    /**
     * Sets the socket or named pipe to connect to MySQL on, if applicable.
     *
     * @param string socket
     * @return $this
     */
    final public function setSocket($val)
    {
        $this->socket = $val;
        return $this;
    }

    /**
     * Retrieves the socket or named pipe we connect to MySQL on, if applicable.
     *
     * @return string
     */
    final public function getSocket()
    {
        return $this->socket;
    }

    /**
     * Sets the name of the database to connect to.
     *
     * @param string database name
     * @return $this
     */
    final public function setDatabaseName($val)
    {
        $this->databaseName = $val;
        return $this;
    }

    /**
     * Retrieves the name of the database we connect to.
     *
     * @return string
     */
    final public function getDatabaseName()
    {
        return $this->databaseName;
    }

    /**
     * Sets the username to use to connect to the database.
     *
     * @param string username
     * @return $this
     */
    final public function setUsername($val)
    {
        $this->username = $val;
        return $this;
    }

    /**
     * Retrieves the username to use to connect to the database.
     *
     * @return string
     */
    final public function getUsername()
    {
        return $this->username;
    }

    /**
     * Sets the password to use to connect to the database.
     *
     * @param string password
     * @return $this
     */
    final public function setPassword($val)
    {
        $this->password = $val;
        return $this;
    }

    /**
     * Retrieves the password to use to connect to the database.
     *
     * @return string
     */
    final public function getPassword()
    {
        return $this->password;
    }

    /**
     * Sets the options bitmask for the database.
     *
     * @param int bitmask of Thaumatic\Junxa::DB_* values
     * @return $this
     */
    final public function setOptions($val)
    {
        $this->options = $val;
        return $this;
    }

    /**
     * Retrieves the options bitmask for the database.
     *
     * @return int bitmask of Thaumatic\Junxa::DB_* values
     */
    final public function getOptions($val)
    {
        return $this->options;
    }

    /**
     * Enables or disables a database option.
     *
     * @param Thaumatic\Junxa::DB_* option to manipulate
     * @param bool whether we want the option on or off
     * @return $this
     */
    final public function setOption($option, $flag)
    {
        if ($flag) {
            $this->options |= $option;
        } else {
            $this->options &= ~$option;
        }
        return $this;
    }

    /**
     * Retrieves whether a database option is enabled.
     *
     * @param Thaumatic_Junxa::DB_* option to check
     * @return bool
     */
    final public function getOption($option)
    {
        return (bool) ($this->options & $option);
    }

    /**
     * Sets the class to use as the table model when no more specific table
     * model is found.
     *
     * @param string class name
     * @return $this
     */
    final public function setDefaultTableClass($val)
    {
        $this->defaultTableClass = $val;
        return $this;
    }

    /**
     * Retrieves the class used as the table model when no more specific table
     * model is found.
     *
     * @return string
     */
    final public function getDefaultTableClass()
    {
        return $this->defaultTableClass;
    }

    /**
     * Sets the class to use as the column model when no more specific column
     * model is found.
     *
     * @param string class name
     * @return $this
     */
    final public function setDefaultColumnClass($val)
    {
        $this->defaultColumnClass = $val;
        return $this;
    }

    /**
     * Retrieves the class used as the column model when no more specific
     * column model is found.
     *
     * @return string
     */
    final public function getDefaultColumnClass()
    {
        return $this->defaultColumnClass;
    }

    /**
     * Sets the class to use as the row model when no more specific row model
     * is found.
     *
     * @param string class name
     * @return $this
     */
    final public function setDefaultRowClass($val)
    {
        $this->defaultRowClass = $val;
        return $this;
    }

    /**
     * Retrieves the class used as the row model when no more specific row
     * model is found.
     *
     * @return string
     */
    final public function getDefaultRowClass()
    {
        return $this->defaultRowClass;
    }

    /**
     * Sets the namespace that will be searched for a class to use for the
     * table model if no more specific table model is found (looking for a
     * class name that is the table name processed by
     * {@see toNameSpaceElement}).
     *
     * @param string namespace prefix, without trailing backslash
     * @return $this
     */
    final public function setAutoTableClassNamespace($val)
    {
        $this->autoTableClassNamespace = $val;
        return $this;
    }

    /**
     * Retrieves the auto table class namespace.
     *
     * @return string
     */
    final public function getAutoTableClassNamespace($val)
    {
        return $this->autoTableClassNamespace;
    }

    /**
     * Sets the namespace that will be searched for a class to use for the
     * column model if no more specific column model is found (looking for a
     * class name that is the column name processed by
     * {@see toNamespaceElement}).
     *
     * @param string namespace prefix, without trailing backslash
     * @return $this
     */
    final public function setAutoColumnClassNamespace($val)
    {
        $this->autoColumnClassNamespace = $val;
        return $this;
    }

    /**
     * Retrieves the auto column class namespace.
     *
     * @return string
     */
    final public function getAutoColumnClassNamespace($val)
    {
        return $this->autoColumnClassNamespace;
    }

    /**
     * Sets the namespace that will be searched for a class to use for the
     * row model if no more specific row model is found (looking for a
     * class name that is the table name processed by
     * {@see toNamespaceElement}).
     *
     * @param string namespace prefix, without trailing backslash
     * @return $this
     */
    final public function setAutoRowClassNamespace($val)
    {
        $this->autoRowClassNamespace = $val;
        return $this;
    }

    /**
     * Retrieves the auto row class namespace.
     *
     * @return string
     */
    final public function getAutoRowClassNamespace($val)
    {
        return $this->autoRowClassNamespace;
    }

    /**
     * @param array<string:string> map of table names to the columns that, in
     * combination with an autoRowClassNamespace, class names for individual
     * rows can be sourced from; for example, if the auto row class namespace
     * is App\Row, the individual row class for the table 'thing' is 'name',
     * and a 'thing' row has 'widget' in the name field, the individual row
     * class (that will be used if it exists) for that row would be
     * App\Row\Thing\Widget
     * @return $this
     */
    final public function setIndividualRowClassColumns(array $val)
    {
        $this->individualRowClassColumns = $val;
        return $this;
    }

    /**
     * @param array<string:string> map of table names to the columns that, in
     * combination with an autoRowClassNamespace, class names for individual
     * rows can be sourced from
     */
    final public function getIndividualRowClassColumns()
    {
        return $this->individualRowClassColumns;
    }

    /**
     * Sets the column that, in combination with an autoRowClassNamespace,
     * class names for individual rows can be sourced from for the specified
     * table.  This is equivalent to one key-value pair in the mapping sent to
     * {@see setIndividualRowClassColumns}.
     *
     * @param string table name
     * @param string column name
     * @return $this
     */
    final public function setIndividualRowClassColumn($table, $column)
    {
        $this->individualRowClassColumns[$table] = $column;
        return $this;
    }

    /**
     * @param string table name
     * @return string|null the individual row class column defined for the
     * specified table by {@see setIndividualRowClassColumns} and/or
     * {@see setIndividualRowClassColumn}, if any
     */
    final public function getIndividualRowClassColumn($table)
    {
        return
            isset($this->individualRowClassColumns[$table])
            ? $this->individualRowClassColumns[$table]
            : null;
    }

    /**
     * Sets a mapping of regular expressions to class names; if no more
     * specific table model is found, this mapping will be searched and
     * for the first regular expression that matches the table name, the
     * corresponding class will be used as the table model.
     *
     * @param array<string:string> map of regular expressions to class names
     * @return $this
     */
    final public function setRegexpTableClasses(array $val)
    {
        $this->regexpTableClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of regexp-based table classes.
     *
     * @return array<string:string>
     */
    final public function getRegexpTableClasses()
    {
        return $this->regexpTableClasses;
    }

    /**
     * Sets the class to be used for the table model for tables whose
     * names match the specified regexp pattern.  This is equivalent
     * to one key-value pair in the mapping sent to
     * {@see setRegexpTableClasses}.
     *
     * @param string the regexp pattern
     * @param string the class name
     * @return $this
     */
    final public function setRegexpTableClass($pattern, $className)
    {
        $this->regexpTableClasses[$pattern] = $className;
        return $this;
    }

    /**
     * @param string regexp pattern
     * @return string|null the table class defined for the specified regexp
     * pattern by {@see setRegexpTableClasses} and/or
     * {@see setRegexpTableClass}, if any
     */
    final public function getRegexpTableClass($pattern)
    {
        return
            isset($this->regexpTableClasses[$pattern])
            ? $this->regexpTableClasses[$pattern]
            : null;
    }

    /**
     * Sets a mapping of regular expressions to class names; if no more
     * specific column model is found, this mapping will be searched and
     * for the first regular expression that matches the table name, the
     * corresponding class will be used as the column model.
     *
     * @param array<string:string> map of regular expressions to class names
     * @return $this
     */
    final public function setRegexpColumnClasses(array $val)
    {
        $this->regexpColumnClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of regexp-based column classes.
     *
     * @return array<string:string>
     */
    final public function getRegexpColumnClasses()
    {
        return $this->regexpColumnClasses;
    }

    /**
     * Sets the class to be used for the column model for columns whose
     * names match the specified regexp pattern.  This is equivalent
     * to one key-value pair in the mapping sent to
     * {@see setRegexpColumnClasses}.
     *
     * @param string the regexp pattern
     * @param string the class name
     * @return $this
     */
    final public function setRegexpColumnClass($pattern, $className)
    {
        $this->regexpColumnClasses[$pattern] = $className;
        return $this;
    }

    /**
     * @param string regexp pattern
     * @return string|null the column class defined for the specified regexp
     * pattern by {@see setRegexpColumnClasses} and/or
     * {@see setRegexpColumnClass}, if any
     */
    final public function getRegexpColumnClass($pattern)
    {
        return
            isset($this->regexpColumnClasses[$pattern])
            ? $this->regexpColumnClasses[$pattern]
            : null;
    }

    /**
     * Sets a mapping of regular expressions to class names; if no more
     * specific row model is found, this mapping will be searched and
     * for the first regular expression that matches the table name, the
     * corresponding class will be used as the row model.
     *
     * @param array<string:string> map of regular expressions to class names
     * @return $this
     */
    final public function setRegexpRowClasses(array $val)
    {
        $this->regexpRowClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of regexp-based row classes.
     *
     * @return array<string:string>
     */
    final public function getRegexpRowClasses()
    {
        return $this->regexpRowClasses;
    }

    /**
     * Sets the class to be used for the table model for rows whose
     * tables' names match the specified regexp pattern.  This is
     * equivalent to one key-value pair in the mapping sent to
     * {@see setRegexpRowClasses}.
     *
     * @param string the regexp pattern
     * @param string the class name
     * @return $this
     */
    final public function setRegexpRowClass($pattern, $className)
    {
        $this->regexpRowClasses[$pattern] = $className;
        return $this;
    }

    /**
     * @param string regexp pattern
     * @return string|null the row class defined for the specified regexp
     * pattern by {@see setRegexpRowClasses} and/or
     * {@see setRegexpRowClass}, if any
     */
    final public function getRegexpRowClass($pattern)
    {
        return
            isset($this->regexpRowClasses[$pattern])
            ? $this->regexpRowClasses[$pattern]
            : null;
    }

    /**
     * Sets a mapping of table names to class names.  If a table name appears
     * in this mapping, the corresponding class name will be used for the
     * table model.  This is the most specific table class specification.
     *
     * @param array<string:string> map of table names to class names
     * @return $this
     */
    final public function setTableClasses(array $val)
    {
        $this->tableClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of explicit table classes.
     *
     * @return array<string:string>
     */
    final public function getTableClasses()
    {
        return $this->tableClasses;
    }

    /**
     * Sets the class to be used for the table model for a particular table.
     * This is equivalent to one key-value pair in the mapping sent to
     * {@see setTableClasses}.
     *
     * @param string the table name
     * @param string the class name
     * @return $this
     */
    final public function setTableClass($tableName, $className)
    {
        $this->tableClasses[$tableName] = $className;
        return $this;
    }

    /**
     * @param string table name
     * @return string|null the table class defined for the specified table by
     * {@see setTableClasses} and/or {@see setTableClass}, if any
     */
    final public function getTableClass($tableName)
    {
        return
            isset($this->tableClasses[$tableName])
            ? $this->tableClasses[$tableName]
            : null;
    }

    /**
     * Sets a mapping of table names to class names.  If a column name appears
     * in this mapping, the corresponding class name will be used for the
     * column model.  This is the most specific column class specification.
     *
     * @param array<string:string> map of column names to class names
     * @return $this
     */
    final public function setColumnClasses(array $val)
    {
        $this->columnClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of explicit column classes.
     *
     * @return array<string:string>
     */
    final public function getColumnClasses()
    {
        return $this->columnClasses;
    }

    /**
     * Sets the class to be used for the column model for columns of a
     * particular name.  This is equivalent to one key-value pair in the
     * mapping sent to {@see setColumnClasses}.
     *
     * @param string the column name
     * @param string the class name
     * @return $this
     */
    final public function setColumnClass($columnName, $className)
    {
        $this->columnClasses[$columnName] = $className;
        return $this;
    }

    /**
     * @param string column name
     * @return string|null the column class defined for the specified
     * column name by {@see setColumnClasses} and/or {@see setColumnClass},
     * if any
     */
    final public function getColumnClass($columnName)
    {
        return
            isset($this->columnClasses[$columnName])
            ? $this->columnClasses[$columnName]
            : null;
    }

    /**
     * Sets a mapping of table names to class names.  If a table name appears
     * in this mapping, the corresponding class name will be used for the
     * row model.  This is the most specific row class specification.
     *
     * @param array<string:string> map of table names to class names
     * @return $this
     */
    final public function setRowClasses(array $val)
    {
        $this->rowClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of explicit row classes.
     *
     * @return array<string:string>
     */
    final public function getRowClasses()
    {
        return $this->rowClasses;
    }

    /**
     * Sets the class to be used for the row model for a particular table.
     * This is equivalent to one key-value pair in the mapping sent to
     * {@see setRowClasses}.
     *
     * @param string the table name
     * @param string the class name
     * @return $this
     */
    final public function setRowClass($tableName, $className)
    {
        $this->rowClasses[$tableName] = $className;
        return $this;
    }

    /**
     * @param string table name
     * @return string|null the row class defined for the specified
     * table by {@see setRowClasses} and/or {@see setRowClass},
     * if any
     */
    final public function getRowClass($tableName)
    {
        return
            isset($this->rowClasses[$tableName])
            ? $this->rowClasses[$tableName]
            : null;
    }

    /**
     * Sets a suffix that will be interpreted as conventionally used in the
     * database to designate a foreign key column; for example, if this is
     * 'Id', then the column 'itemId' will be interpreted as a foreign key
     * into the table 'item'.
     *
     * @param string foreign key suffix
     * @return $this
     */
    final public function setForeignKeySuffix($val)
    {
        $this->foreignKeySuffix = $val;
        $this->foreignKeySuffixPattern =
            $this->foreignKeySuffix === null
            ? null
            : (
                '/^(.+)'
                . preg_quote($this->foreignKeySuffix, '/')
                . '$/'
            )
        ;
        return $this;
    }

    /**
     * Retrieves the foreign key suffix, if any.
     *
     * @return string|null
     */
    final public function getForeignKeySuffix()
    {
        return $this->foreignKeySuffix;
    }

    /**
     * Matches a column name against the foreign key suffix pattern, and if
     * the column name ends in the pattern, returns the base part of the
     * column name without the suffix.
     *
     * @param string column name
     * @return string|null
     */
    final public function getForeignKeySuffixMatch($columnName)
    {
        if ($this->foreignKeySuffixPattern === null) {
            return null;
        }
        if (preg_match($this->foreignKeySuffixPattern, $columnName, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * Sets the plural to singular map to use for overriding the default
     * grammatical inflection behavior.
     *
     * @param array<string:string> plural to singular map
     * @return $this
     */
    final public function setPluralToSingularMap(array $val)
    {
        $this->pluralToSingularMap = $val;
        return $this;
    }

    /**
     * Retrieves the plural to singular map used for overriding the default
     * grammatical inflection behavior.
     *
     * @return array<string:string> plural to singular map
     */
    final public function getPluralToSingularMap()
    {
        return $this->pluralToSingularMap;
    }

    /**
     * Sets the singular for a given plural in the plural to singular map.
     *
     * @param string plural
     * @param string singular
     * @return $this
     */
    final public function setPluralToSingularMapping($plural, $singular)
    {
        $this->pluralToSingularMap[$plural] = $singular;
        return $this;
    }

    /**
     * Retrieves the specifically-mapped singular for the given plural,
     * if any.  (Does *not* perform non-mapped grammatical inflection.)
     *
     * @param string plural
     * @return string|null
     */
    final public function getPluralToSingularMapping($plural)
    {
        return
            isset($this->pluralToSingularMap[$plural])
            ? $this->pluralToSingularMap[$plural]
            : null
        ;
    }

    /**
     * Sets the singular to plural map to use for overriding the default
     * grammatical inflection behavior.
     *
     * @param array<string:string> singular to plural map
     * @return $this
     */
    final public function setSingularToPluralMap(array $val)
    {
        $this->singularToPluralMap = $val;
        return $this;
    }

    /**
     * Retrieves the singular to plural map used for overriding the default
     * grammatical inflection behavior.
     *
     * @return array<string:string> singular to plural map
     */
    final public function getSingularToPluralMap()
    {
        return $this->singularToPluralMap;
    }

    /**
     * Sets the plural for a given singular in the singular to plural map.
     *
     * @param string singular
     * @param string plural
     * @return $this
     */
    final public function setSingularToPluralMapping($singular, $plural)
    {
        $this->singularToPluralMap[$singular] = $plural;
        return $this;
    }

    /**
     * Retrieves the specifically-mapped plural for the given singular,
     * if any.  (Does *not* perform non-mapped grammatical inflection.)
     *
     * @param string plural
     * @return string|null
     */
    final public function getSingularToPluralMapping($singular)
    {
        return
            isset($this->singularToPluralMap[$singular])
            ? $this->singularToPluralMap[$singular]
            : null
        ;
    }

    /**
     * Sets the locale to use for grammatical inflection (see
     * {@link https://github.com/ICanBoogie/Inflector} for supported locales).
     *
     * @param string locale
     * @return $this
     */
    final public function setInflectionLocale($val)
    {
        $this->inflectionLocale = $val;
        $this->inflector = null;
        return $this;
    }

    /**
     * Retrieves the locale to use for grammatical inflection.
     *
     * @return string
     */
    final public function getInflectionLocale()
    {
        return $this->inflectionLocale;
    }

    /**
     * Retrieves the grammatical inflection interface object, instancing it if
     * necessary.
     *
     * @return ICanBoogie\Inflector
     */
    private function getInflector()
    {
        if ($this->inflector === null) {
            $this->inflector = Inflector::get($this->getInflectionLocale());
        }
        return $this->inflector;
    }

    /**
     * Retrieves the singular version of a given plural noun, as best we cna
     * determine.
     *
     * @param string plural noun
     * @return string singular noun
     */
    final public function getSingularFromPlural($plural)
    {
        $singular = $this->getPluralToSingularMapping($plural);
        if ($singular !== null) {
            return $singular;
        }
        return $this->getInflector()->singularize($plural);
    }

    /**
     * Retrieves the plural version of a given singular noun, as best we cna
     * determine.
     *
     * @param string singular noun
     * @return string plural noun
     */
    final public function getPluralFromSingular($singular)
    {
        $plural = $this->getSingularToPluralMapping($singular);
        if ($plural !== null) {
            return $plural;
        }
        return $this->getInflector()->pluralize($singular);
    }

    /**
     * Given a property name, returns a table model if there is a table in this
     * database for which the property name would constitute a reasonable name
     * under which to retrieve multiple rows based on a one-to-many foreign key
     * relationship.
     *
     * @param string property name
     * @return Thaumatic\Junxa\Table|null
     */
    final public function getChildTableFromPropertyName($propertyName)
    {
        if ($this->getOption(self::DB_TABLES_ARE_PLURALS)) {
            if ($this->tableExists($propertyName)) {
                return $this->table($propertyName);
            }
        } else {
            $singular = $this->getSingularFromPlural($propertyName);
            if ($this->tableExists($singular)) {
                return $this->table($singular);
            }
        }
        return null;
    }

    /**
     * Sets an alternate Junxa instance to forward changes (any queries other
     * than SELECTs or SHOWs) to.  This is to support primary/secondary
     * replication architectures; Junxa can be configured to read from the
     * secondary(ies) and write to the primary.
     *
     * @param Thaumatic\Junxa|array Junxa instance or array configuration for
     * Junxa instance (will be instanced on demand)
     * @return $this
     */
    final public function setChangeHandler($val)
    {
        $this->changeHandler = $val;
        return $this;
    }

    /**
     * Retrieves the change handler configuration.
     *
     * @return Thaumatic\Junxa|array
     */
    final public function getChangeHandler()
    {
        return $this->changeHandler;
    }

    /**
     * Retrieves the alternate Junxa instance to send database changes to, if
     * any.
     *
     * @return Thaumatic\Junxa|false
     * @throws Thaumatic\Junxa\Exception\JunxaConfigurationException if the
     * change handler configuration is invalid
     */
    final public function getChangeHandlerObject()
    {
        if ($this->changeHandlerObject === null) {
            if ($this->changeHandler === null) {
                $this->changeHandlerObject = false;
            } elseif ($this->changeHandler instanceof Junxa) {
                $this->changeHandlerObject = $this->changeHandler;
            } elseif (!is_array($this->changeHandler)) {
                throw new JunxaConfigurationException('invalid change handler');
            } else {
                $def = $this->changeHandler;
                if (isset($def['class'])) {
                    $this->changeHandlerObject = new $def['class']($def);
                } else {
                    $this->changeHandlerObject = new self($def);
                }
            }
        }
        return $this->changeHandlerObject;
    }

    /**
     * Connect to the database.
     *
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException if the object's configuration is invalid
     */
    final public function connect()
    {
        if (!$this->databaseName) {
            throw new JunxaConfigurationException('database to connect to has not been specified');
        }
        $this->link = new \mysqli(
            (
                $this->getOption(self::DB_PERSISTENT_CONNECTION)
                ? 'p:' . $this->hostname
                : $this->hostname
            ),
            $this->username,
            $this->password,
            $this->databaseName,
            $this->port,
            $this->socket
        );
        return $this;
    }

    /**
     * Retrieves the class to use as the table model for a given table.
     *
     * @param string the table name
     * @return string
     */
    final public function tableClass($table)
    {
        if (isset($this->tableClasses[$table])) {
            return $this->tableClasses[$table];
        }
        foreach ($this->regexpTableClasses as $name => $class) {
            if (preg_match($name, $table)) {
                return $class;
            }
        }
        if ($this->autoTableClassNamespace !== null) {
            $name = $this->autoTableClassNamespace . '\\' . self::toNamespaceElement($table);
            if (class_exists($name)) {
                return $name;
            }
        }
        if ($this->defaultTableClass !== null) {
            return $this->defaultTableClass;
        }
        return 'Thaumatic\Junxa\Table';
    }

    /**
     * Retrieves the class to use as the column model for a given column.
     *
     * @param string the column name
     * @return string
     */
    final public function columnClass($column)
    {
        if (isset($this->columnClasses[$column])) {
            return $this->columnClasses[$column];
        }
        foreach ($this->regexpColumnClasses as $name => $class) {
            if (preg_match($name, $column)) {
                return $class;
            }
        }
        if ($this->autoColumnClassNamespace !== null) {
            $name = $this->autoColumnClassNamespace . '\\' . self::toNamespaceElement($column);
            if (class_exists($name)) {
                return $name;
            }
        }
        if ($this->defaultColumnClass !== null) {
            return $this->defaultColumnClass;
        }
        return 'Thaumatic\Junxa\Column';
    }

    /**
     * Retrieves the class to use as the row model for a given table
     * and, optionally, row data.
     *
     * @param string the table name
     * @param array<string:mixed> the row data for the row to be modeled, if
     * available
     * @return string
     */
    final public function rowClass($table, array $rowData = null)
    {
        if (isset($this->rowClasses[$table])) {
            return $this->rowClasses[$table];
        }
        foreach ($this->regexpRowClasses as $name => $class) {
            if (preg_match($name, $table)) {
                return $class;
            }
        }
        if ($this->autoRowClassNamespace !== null) {
            $mainName = $this->autoRowClassNamespace . '\\' . self::toNamespaceElement($table);
            if ($rowData !== null) {
                $src = $this->getIndividualRowClassColumn($table);
                if ($src !== null && isset($rowData[$src])) {
                    $rowName = $mainName . '\\' . self::toNamespaceElement($rowData[$src]);
                    if (class_exists($rowName)) {
                        return $rowName;
                    }
                }
            }
            if (class_exists($mainName)) {
                return $mainName;
            }
        }
        if ($this->defaultRowClass !== null) {
            return $this->defaultRowClass;
        }
        return 'Thaumatic\Junxa\Row';
    }

    /**
     * Retrieves the names of the tables attached to this database.
     *
     * @return array<string>
     */
    final public function tables()
    {
        return $this->tables;
    }

    /**
     * Retrieves whether a table with the specified name is attached to this database.
     *
     * @param string the table name
     * @return bool
     */
    final public function tableExists($table)
    {
        return in_array($table, $this->tables);
    }

    /**
     * Retrieves a model of the specified table.
     *
     * @param string
     * @return Thaumatic\Junxa\Table
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException if the table does not exist
     */
    final public function table($name)
    {
        if (!isset($this->tableModels[$name])) {
            if (!in_array($name, $this->tables)) {
                $this->determineTables();
                if (!in_array($name, $this->tables)) {
                    throw new JunxaNoSuchTableException($name);
                }
            }
            $class = $this->tableClass($name);
            $this->tableModels[$name] = new $class($this, $name);
        }
        return $this->tableModels[$name];
    }

    /**
     * Loads one or more tables onto the database model, each specified as an
     * argument.
     *
     * @param string... the names of tables to load; if none are specified,
     * load all tables
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException if a table specified does not exist
     */
    final public function loadTables()
    {
        $baseTables = func_get_args();
        switch (count($baseTables)) {
            case 0:
                $baseTables = $this->tables;
                break;
            case 1:
                if (is_array($baseTables[0])) {
                    $baseTables = $baseTables[0];
                }
                break;
        }
        $tables = [];
        foreach ($baseTables as $baseTable) {
            if (!isset($this->tableModels[$baseTable])) {
                $tables[] = $baseTable;
            }
        }
        if (!$tables) {
            return;
        }
        $tablesRescanned = false;
        for ($index = 0; $index < count($tables); $index++) {
            if (!in_array($tables[$index], $this->tables)) {
                if (!$tablesRescanned) {
                    $this->determineTables();
                    $tablesRescanned = true;
                }
                if (!in_array($tables[$index], $this->tables)) {
                    throw new JunxaNoSuchTableException($tables[$index]);
                }
            }
            $tableIndices[$tables[$index]] = $index;
        }
        $res = $this->query("SELECT *\n\tFROM " . join(', ', $tables) . "\n\tLIMIT 0", self::QUERY_RAW);
        $fieldSets = [];
        for ($i = 0, $j = $res->field_count; $i < $j; $i++) {
            $tableIndex = $tableIndices[$field->table];
            $fieldSets[$tableIndex][] = $res->fetch_field();
        }
        $res->free();
        for ($index = 0; $index < count($tables); $index++) {
            $table = $tables[$index];
            $class = $this->tableClass($table);
            $this->tableModels[$table] = new $class($this, $table, count($fieldSets[$index]), $fieldSets[$index]);
        }
        return $this;
    }

    /**
     * @return string HTML-formatted report on the database's query statistics
     */
    final public function reportQueryStatistics()
    {
        $stats = $this->queryStatistics;
        arsort($stats);
        $globalStats = self::$overallQueryStatistics;
        arsort($globalStats);
        $out = '';
        foreach ($stats as $key => $val) {
            if (!$anyStats) {
                $out .= "Query statistics from database called:<br>\n";
                $anyStats = true;
            }
            $out .= "<pre>$val: $key</pre>\n";
            $globalStats[$key] -= $val;
        }
        foreach ($globalStats as $key => $val) {
            if (!$val) {
                continue;
            }
            if (!$anyGlobalStats) {
                $out .= "Additional query statistics generated through copies of database or other databases:<br>\n";
                $anyGlobalStats = true;
            }
            $out .= "<pre>$val: $key</pre>\n";
        }
        return $out;
    }

    final public function query($query = null, $mode = 0, $emptyOkay = false)
    {
        if ($query === null) {
            return new QueryBuilder($this);
        }
        $isResult = false;
        $this->queryStatus = self::RESULT_FAILURE;
        $this->queryMessage = '';
        $insertIgnore = false;
        $update = false;
        $delete = false;
        $errorOkay = false;
        $echo = false;
        $queryBuilder = null;
        switch (gettype($query)) {
            case 'string':
                if (preg_match('/^\s*(SELECT|SHOW)\s*/is', $query)) {
                    $isResult = true;
                } else {
                    $handler = $this->getChangeHandlerObject();
                    if ($handler) {
                        $result = $handler->query($query, $mode, $emptyOkay);
                        $this->queryStatus = $handler->getQueryStatus();
                        $this->queryMessage = $handler->getQueryMessage();
                        $this->insertId = $handler->getInsertId();
                        return $result;
                    } else {
                        if (preg_match('/^\s*UPDATE\s+/is', $query)) {
                            $update = true;
                        } elseif (preg_match('/^\s*DELETE\s+/is', $query)) {
                            $delete = true;
                        } elseif (preg_match('/^\s*INSERT[^(]+IGNORE\s+/is', $query)) {
                            $insertIgnore = true;
                        }
                    }
                }
                break;
            case 'array':
                $query = new QueryBuilder($this, null, $query);
                // fallthrough
            case 'object':
                if (!($query instanceof QueryBuilder)) {
                    throw new JunxaInvalidQueryException(
                        'object query must be a '
                        . 'Thaumatic\Junxa\Query\Builder, '
                        . ' got ' . get_class($query)
                    );
                }
                $queryBuilder = $query;
                $query->validate();
                if ($mode === 0) {
                    $mode = $query->getMode();
                }
                $queryType = $query->getType();
                if ($queryType === 'select' || $queryType === 'show') {
                    $isResult = true;
                    $useChangeHandler = $query->getOption(self::OPTION_FORCE_USE_CHANGE_HANDLER);
                } else {
                    $useChangeHandler = true;
                }
                if ($useChangeHandler) {
                    $handler = $this->getChangeHandlerObject();
                    if ($handler) {
                        $result = $handler->query($query, $mode, $emptyOkay);
                        $this->queryStatus = $handler->getQueryStatus();
                        $this->queryMessage = $handler->getQueryMessage();
                        $this->insertId = $handler->getInsertId();
                        return $result;
                    }
                }
                if ($query->getOption(self::OPTION_EMPTY_OKAY)) {
                    $emptyOkay = true;
                }
                if ($query->getOption(self::OPTION_ERROR_OKAY)) {
                    $errorOkay = true;
                }
                if ($query->getOption(self::OPTION_DEBUG_ECHO)) {
                    $echo = true;
                }
                if ($queryType === 'update') {
                    $update = true;
                } elseif ($queryType === 'delete') {
                    $delete = true;
                } elseif ($queryType === 'insert' && $query->getOption(self::OPTION_IGNORE)) {
                    $insertIgnore = true;
                }
                $query = $query->express();
                $queryBuilder->processSql($query);
                break;
            default:
                throw new JunxaInvalidQueryException('invalid argument to query()');
        }
        if ($this->getOption(self::DB_COLLECT_QUERY_STATISTICS)) {
            $this->queryStatistics[$query]++;
            self::$overallQueryStatistics[$query]++;
        }
        if ($echo) {
            echo $query;
        }
        if (!$mode) {
            $mode = $isResult ? self::QUERY_OBJECTS : self::QUERY_FORGET;
        }
        if ($this->isEventDispatcherLoaded()) {
            $event = new JunxaQueryEvent($this, $query, $queryBuilder);
            $this->getEventDispatcher()->dispatch(JunxaQueryEvent::NAME, $event);
            if ($event->getPreventQuery()) {
                $this->queryStatus = self::RESULT_PREVENTED;
                switch ($mode) {
                    case self::QUERY_SINGLE_ASSOC:
                    case self::QUERY_SINGLE_ARRAY:
                    case self::QUERY_SINGLE_OBJECT:
                    case self::QUERY_SINGLE_CELL:
                        return null;
                    default:
                        return [];
                }
            }
        }
        $res = $this->link->query($query);
        if ($res) {
            if ($insertIgnore && !$emptyOkay && $this->getAffectedRows() <= 0) {
                $this->queryStatus = self::RESULT_INSERT_FAIL;
            } elseif ($update && !$emptyOkay && $this->getAffectedRows() <= 0) {
                $this->queryStatus = self::RESULT_UPDATE_FAIL;
            } elseif ($delete && !$emptyOkay && $this->getAffectedRows() <= 0) {
                $this->queryStatus = self::RESULT_DELETE_FAIL;
            } else {
                $this->queryStatus = self::RESULT_SUCCESS;
            }
        } else {
            $this->queryMessage = $this->link->error;
            $errno = $this->link->errno;
            if ($errno === 2006 || $errno === 2013) {
                usleep(1000);
                $this->connect();
                return $this->query($query, $mode, $emptyOkay);
            }
            $this->queryStatus = self::RESULT_FAILURE;
            if (!$errorOkay) {
                throw new JunxaQueryExecutionException($this->queryMessage . ' from ' . $query);
            }
        }
        if (!$isResult && preg_match('/^\s*(INSERT|REPLACE)\b/i', $query)) {
            $this->insertId = $this->link->insert_id;
        }
        if (!$res || !$isResult) {
            switch ($mode) {
                case self::QUERY_RAW:
                case self::QUERY_FORGET:
                    return $res;
                case self::QUERY_SINGLE_ASSOC:
                case self::QUERY_SINGLE_ARRAY:
                case self::QUERY_SINGLE_OBJECT:
                case self::QUERY_SINGLE_CELL:
                    return null;
                default:
                    return [];
            }
        }
        $out = null;
        switch ($mode) {
            case self::QUERY_RAW:
                return $res;
            case self::QUERY_FORGET:
                $out = $res;
                break;
            case self::QUERY_ASSOCS:
                $out = [];
                while ($row = $res->fetch_array(MYSQLI_ASSOC)) {
                    $out[] = $row;
                }
                break;
            case self::QUERY_ARRAYS:
                $out = [];
                while ($row = $res->fetch_array(MYSQLI_NUM)) {
                    $out[] = $row;
                }
                break;
            case self::QUERY_DUAL_ARRAYS:
                $out = [];
                while ($row = $res->fetch_array(MYSQLI_BOTH)) {
                    $out[] = $row;
                }
                break;
            case self::QUERY_OBJECTS:
                $out = [];
                while ($row = $res->fetch_object()) {
                    $out[] = $row;
                }
                break;
            case self::QUERY_SINGLE_ASSOC:
                if ($res->num_rows !== 1 && (!$emptyOkay || $res->num_rows !== 0)) {
                    throw new JunxaInvalidQueryException(
                        'QUERY_SINGLE_ASSOC had ' . $res->num_rows . ' rows'
                    );
                }
                if ($res->num_rows > 0) {
                    $out = $res->fetch_array(MYSQLI_ASSOC);
                }
                break;
            case self::QUERY_SINGLE_ARRAY:
                if ($res->num_rows !== 1 && (!$emptyOkay || $res->num_rows !== 0)) {
                    throw new JunxaInvalidQueryException(
                        'QUERY_SINGLE_ARRAY had ' . $res->num_rows . ' rows'
                    );
                }
                if ($res->num_rows > 0) {
                    $out = $res->fetch_array(MYSQLI_NUM);
                }
                break;
            case self::QUERY_SINGLE_OBJECT:
                if ($res->num_rows !== 1 && (!$emptyOkay || $res->num_rows !== 0)) {
                    throw new JunxaInvalidQueryException(
                        'QUERY_SINGLE_OBJECT had ' . $res->num_rows . ' rows'
                    );
                }
                if ($res->num_rows > 0) {
                    $out = $res->fetch_object();
                }
                break;
            case self::QUERY_SINGLE_CELL:
                if ($res->num_rows !== 1 && (!$emptyOkay || $res->num_rows !== 0)) {
                    throw new JunxaInvalidQueryException(
                        'QUERY_SINGLE_CELL had ' . $res->num_rows . ' rows'
                    );
                }
                if ($res->num_rows > 0) {
                    $row = $res->fetch_array(MYSQLI_NUM);
                    if (count($row) !== 1) {
                        throw new JunxaInvalidQueryException(
                            'QUERY_SINGLE_CELL had row with '
                            . count($row)
                            . ' columns'
                        );
                    }
                    $out = $row[0];
                }
                break;
            case self::QUERY_COLUMN_ASSOC:
                $out = [];
                if ($res->num_rows > 0) {
                    $row = $res->fetch_array(MYSQLI_NUM);
                    if (count($row) != 2) {
                        throw new JunxaInvalidQueryException(
                            'QUERY_COLUMN_ASSOC had row with '
                            . count($row)
                            . ' columns'
                        );
                    }
                    do {
                        $out[$row[0]] = $row[1];
                    } while ($row = $res->fetch_array(MYSQLI_NUM));
                }
                break;
            case self::QUERY_COLUMN_ARRAY:
                $out = [];
                if ($res->num_rows > 0) {
                    $row = $res->fetch_array(MYSQLI_NUM);
                    if (count($row) != 1) {
                        throw new JunxaInvalidQueryException(
                            'QUERY_COLUMN_ARRAY had row with '
                            . count($row)
                            . ' columns'
                        );
                    }
                    do {
                        $out[] = $row[0];
                    } while ($row = $res->fetch_array(MYSQLI_NUM));
                }
                break;
            case self::QUERY_COLUMN_OBJECT:
                $out = new stdClass;
                if ($res->num_rows > 0) {
                    $row = $res->fetch_array(MYSQLI_NUM);
                    if (count($row) != 2) {
                        throw new JunxaInvalidQueryException(
                            'QUERY_COLUMN_OBJECT had row with '
                            . count($row)
                            . ' columns'
                        );
                    }
                    do {
                        $out->{$row[0]} = $row[1];
                    } while ($row = $res->fetch_array(MYSQLI_NUM));
                }
                break;
            default:
                throw new JunxaInvalidQueryException(
                    'invalid query mode '
                    . (is_scalar($mode) ? $mode : gettype($mode))
                );
        }
        $res->free();
        return $out;
    }

    /**
     * Determines and validates the names of the tables present in the
     * database, storing the list on this model.
     *
     * @return $this
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidIdentifierException if
     * any of the table names are invalid identifiers
     */
    final public function determineTables()
    {
        $this->tables = [];
        $res = $this->link->query('SHOW TABLES');
        while ($row = $res->fetch_array(MYSQLI_NUM)) {
            $table = $row[0];
            self::validateIdentifier($table);
            $this->tables[] = $table;
        }
        return $this;
    }

    /**
     * @return numeric the number of rows affected by the last query
     */
    final public function getAffectedRows()
    {
        return $this->link->affected_rows;
    }

    /**
     * Property-mode accessor for tables.
     *
     * @param string table name
     * @return Thaumatic\Junxa\Table
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException if the table does not exist
     */
    final public function __get($name)
    {
        return $this->table($name);
    }

    /**
     * Property-mode mutator.  Exists only to throw exceptions if attempts
     * are made to set non-class properties on this model.
     *
     * @param string property name
     * @param mixed property value
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException
     */
    final public function __set($name, $value)
    {
        throw new JunxaConfigurationException('cannot set property ' . $name);
    }

    /**
     * Retrieves the result code for the last query performed.
     *
     * @var int Thaumatic\Junxa::RESULT_*
     */
    final public function getQueryStatus()
    {
        return $this->queryStatus;
    }

    /**
     * Retrieves the error result for the last query performed.
     *
     * @var string
     */
    final public function getQueryMessage()
    {
        return $this->queryMessage;
    }

    /**
     * Retrieves the last insert ID from a database query.
     *
     * @return numeric
     */
    final public function getInsertId()
    {
        return $this->insertId;
    }

    /**
     * Retrieves the database-model-specific statistical array of how many
     * times a given query is run.  This is only populated by database models
     * with the option Junxa::DB_COLLECT_QUERY_STATISTICS enabled.
     *
     * @return array<string:int>
     */
    final public function getQueryStatistics()
    {
        return $this->queryStatistics;
    }

    /**
     * Retrieves the class-general statistical array of how many times a given
     * query is run.  This is only populated by database models with the
     * option Junxa::DB_COLLECT_QUERY_STATISTICS enabled.
     *
     * @return array<string:int>
     */
    final public function getOverallQueryStatisticsDynamic()
    {
        return self::$overallQueryStatistics;
    }

    /**
     * Retrieves the class-general statistical array of how many times a given
     * query is run.  This is only populated by database models with the option
     * Junxa::DB_COLLECT_QUERY_STATISTICS enabled.
     *
     * @return array<string:int>
     */
    final public static function getOverallQueryStatistics()
    {
        return self::$overallQueryStatistics;
    }

    /**
     * Retrieves (instancing if necessary) the database model's event
     * dispatcher.
     *
     * @return Symfony\Component\EventDispatcher\EventDispatcher
     */
    final public function getEventDispatcher()
    {
        if (!$this->eventDispatcher) {
            $this->eventDispatcher = new EventDispatcher;
        }
        return $this->eventDispatcher;
    }

    /**
     * Returns whether the database currently has an instanced event
     * dispatcher.
     *
     * @return bool
     */
    final public function isEventDispatcherLoaded()
    {
        return $this->eventDispatcher !== null;
    }

    /**
     * Resolves Junxa query structures into SQL text.
     *
     * @param mixed the data to be resolved
     * @param Thaumatic\Junxa\Query\Builder the current query builder object
     * @param string the statement context in which the data is being resolved
     * @param Thaumatic\Junxa\Column the column, if any, which the data is
     * being prepared for
     * @param Thaumatic\Junxa\Query\Builder the parent query, if any
     */
    final public function resolve($item, QueryBuilder $query, $context, $column, $parent)
    {
        if (is_array($item)) {
            $elem = [];
            $ix = 0;
            foreach ($item as $subitem) {
                $elem[$ix++] = $this->resolve($subitem, $query, $context, $column, $parent);
            }
            if ($context === 'join') {
                $keys = array_keys($item);
                $out = $elem[0];
                for ($i = 1; $i < count($keys); $i++) {
                    if ($item[$keys[$i]] instanceof Table && $item[$keys[$i - 1]] instanceof Table) {
                        $out .= ', ' . $elem[$i];
                    } else {
                        $out .= ' ' . $elem[$i];
                    }
                }
                return $out;
            } else {
                return join(', ', $elem);
            }
        } elseif (is_object($item) && method_exists($item, 'express')) {
            return $item->express($query, $context, $column, $parent);
        } elseif ($column) {
            return $column->represent($item, $query, $context);
        } else {
            return $this->quote($item);
        }
    }

    /**
     * Escapes data for presentation to the database engine.
     *
     * @param mixed the data to escape
     * @return string|numeric
     */
    final public function quote($data)
    {
        if (!isset($data)) {
            return 'NULL';
        }
        if (is_object($data)) {
            if ($data instanceof Row && isset($data->id)) {
                $data = $data->id;
            } else {
                throw new JunxaInvalidQueryException(
                    'cannot use ' . get_class($data) . ' as raw data'
                );
            }
        }
        if (is_numeric($data)) {
            return $data;
        }
        if (is_bool($data)) {
            return $data ? 1 : 0;
        }
        if (is_object($data) && method_exists($data, '__toString')) {
            $data = strval($data);
        }
        if (!is_string($data)) {
            throw new JunxaInvalidQueryException(
                'cannot use ' . gettype($data) . ' as raw data'
            );
        }
        return "'" . $this->escapeString($data) . "'";
    }

    /**
     * Escapes text presentation to the database engine, without quoting.
     *
     * @param string the text to escape
     * @return string
     */
    final public function escapeString($text)
    {
        return $this->link->real_escape_string($text);
    }

    /**
     * Retrieves whether a given database model addresses the same database
     * as this one.
     *
     * @param Thaumatic\Junxa the database to check
     * @return bool
     */
    final public function isSame($database)
    {
        if ($database === $this) {
            return true;
        }
        if ($this->databaseName !== $database->getDatabaseName()) {
            return false;
        }
        if ($this->hostname !== $database->getHostname()) {
            return false;
        }
        if ($this->port !== $database->getPort()) {
            return false;
        }
        if ($this->socket !== $database->getSocket()) {
            return false;
        }
        return true;
    }

    /**
     * Standard magic method: when a Junxa object is deserialized, reconnect it to its database.
     *
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException if the object's configuration is invalid
     */
    public function __wakeup()
    {
        $this->connect();
    }

    /**
     * Derive (lossily) text that can be present in the PHP class namespace
     * from specified text.
     *
     * Sample results:
     *   name       => Name
     *   someName   => SomeName
     *   some_name  => SomeName
     *   some name  => SomeName
     *   4 name     => _4Name
     *   x 4 name   => X4Name
     *
     * @param string text to convert
     * @return string
     */
    final public static function toNamespaceElement($text)
    {
        $text = preg_replace('/\W+/', '_', $text);
        $text = self::underscoresToPascalCase($text);
        if (preg_match('/^\d/', $text)) {
            $text = '_' . $text;
        }
        return $text;
    }

    /**
     * Converts underscore-separated text to Pascal case.
     *
     * @param string text to convert
     * @return string
     */
    final public static function underscoresToPascalCase($text)
    {
        return ucfirst(
            preg_replace_callback(
                '/_([^_])/',
                function ($match) {
                    return ucfirst($match[1]);
                },
                $text
            )
        );
    }

    /**
     * Returns whether the result code passed as its argument indicates a
     * successful query.  Since there are several result codes which indicate
     * "success" along with other result information, this function should be
     * used as a general "okayness" check.
     *
     *      if (!Junxa::OK($row->insert())) {
     *          throw new Exception('insert failed');
     *      }
     *
     *      $res = $row1->save();
     *      if (Junxa::OK($res) && $res !== Junxa::RESULT_UPDATE_NOOP) {
     *          $row2->save();
     *      }
     *
     * @param int the result code to check
     * @return bool
     */
    final public static function OK($code)
    {
        return $code > 0;
    }

    /**
     * Validates that Junxa can use the specified identifier as the name
     * of a table or column.  The identifiers that generate exceptions are:
     *
     * 1) Identifiers starting with "junxaInternal".  This is so that
     * Junxa models can use properties with names beginning with underscores
     * for their own purposes while loading database-defined information
     * into dynamic object properties.
     * 2) Identifiers that are PHP keywords.  This is because if you, for
     * example, were try to use Junxa to access a database table called
     * "isset" using syntax like $db->isset, this would be a syntax error.
     *
     * @throws Thaumatic\Junxa\Exceptions\JunxaInvalidIdentifierException if
     * Junxa cannot represent the specified identifier
     */
    final public static function validateIdentifier($identifier)
    {
        if ($identifier[0] === 'j' && preg_match('/^junxaInternal/', $identifier)) {
            throw new JunxaInvalidIdentifierException($identifier);
        }
        if (in_array(strtolower($identifier), self::PHP_KEYWORDS)) {
            throw new JunxaInvalidIdentifierException($identifier);
        }
    }

}
