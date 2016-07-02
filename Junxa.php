<?php

namespace Thaumatic;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaConfigurationException;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException;
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
     * @const int database-level behavioral option: load all tables at
     * initialization
     */
    const DB_PRELOAD_TABLES             = 0x00000001;

    /**
     * @const int database-level behavioral option: cache row results from
     * tables
     */
    const DB_CACHE_TABLE_ROWS           = 0x00000002;

    /**
     * @const int database-level behavioral option: collect statistics on
     * queries executed
     */
    const DB_COLLECT_QUERY_STATISTICS   = 0x00000004;

    /**
     * @const int database-level behavioral option: use a persistent connection
     */
    const DB_PERSISTENT_CONNECTION      = 0x00000008;

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
     * @const int query result code: a table row merge was called for but the
     * table did not have any non-primary unique key to base a merge on
     */
    const RESULT_MERGE_NOKEY            = -6;

    /**
     * @const int query result code: a table row delete was called for but the
     * row did not have the primary key information necessary to automatically
     * generate a delete
     */
    const RESULT_DELETE_FAIL            = -7;

    /**
     * @const int query result code: a table row find was called for and no
     * matching rows were found
     */
    const RESULT_FIND_FAIL              = -8;

    /**
     * @const int query result code: an INSERT IGNORE query was executed and no
     * rows were affected
     */
    const RESULT_INSERT_FAIL            = -9;

    /**
     * @const int query result code: an UPDATE query affected no rows
     */
    const RESULT_UPDATE_FAIL            = -10;

    /**
     * @var string the hostname to connect to MySQL on
     */
    private $hostname;

    /**
     * @var string the name of the MySQL database to connect to
     */
    private $database;

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
     * @var Thaumatic\Junxa the object to send changes to, either the same as $changeHandler or built based on it
     */
    private $changeHandlerObject;

    /**
     * @var int bitmask of Thaumatic\Junxa::DB_* values for Junxa's general behavior
     */
    private $options = 0;

    /**
     * @var string a namespace that can be expected to be populated with column model classes that Junxa should use
     */
    private $autoColumnClassNamespace;

    /**
     * @var string a namespace that can be expected to be populated with row model classes that Junxa should use
     */
    private $autoRowClassNamespace;

    /**
     * @var string a namespace that can be expected to be populated with table model classes that Junxa should use
     */
    private $autoTableClassNamespace;

    /**
     * @var array<string:string> tracking array for classes to use as models for columns by name
     */
    private $columnClasses = [];

    /**
     * @var array<string:string> tracking array for classes to use as models for tables by name
     */
    private $tableClasses = [];

    /**
     * @var array<string:string> tracking array for classes to use as models for rows by name
     */
    private $rowClasses = [];

    /**
     * @var array<string:string> tracking array for classes to use as models for columns by regexp pattern
     */
    private $regexpColumnClasses = [];

    /**
     * @var array<string:string> tracking array for classes to use as models for rows by regexp pattern
     */
    private $regexpRowClasses = [];

    /**
     * @var array<string:string> tracking array for classes to use as models for tables by regexp pattern
     */
    private $regexpTableClasses = [];

    /**
     * @var string the name of the default class to use for column models if one is not otherwise found
     */
    private $defaultColumnClass;

    /**
     * @var string the name of the default class to use for row models if one is not otherwise found
     */
    private $defaultRowClass;

    /**
     * @var string the name of the default class to use for table models if one is not otherwise found
     */
    private $defaultTableClass;

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
     * @var array<string:int> per-instance statistical array of how many times a given query is run
     */
    private $queryStatistics = [];

    /**
     * @var array<string:int> class-general statistical array of how many times a given query is run
     */
    private static $overallQueryStatistics = [];

    /**
     * @var bool class level flag for echoing queries for debugging
     */
    private static $echo = false;

    /**
     * Static factory method.
     *
     * @param array<string:mixed> array of configuration parameters; see set functions for each for details
     */
    public static function make(array $def = null)
    {
        return new self($def);
    }

    /**
     * Constructor.
     *
     * @param array<string:mixed> array of configuration parameters; see set functions for each for details
     */
    public function __construct(array $def = null)
    {
        if ($def !== null) {
            if (array_key_exists('hostname', $def)) {
                $this->setHostname($def['hostname']);
            }
            if (array_key_exists('database', $def)) {
                $this->setDatabase($def['database']);
            }
            if (array_key_exists('username', $def)) {
                $this->setUsername($def['username']);
            }
            if (array_key_exists('password', $def)) {
                $this->setPassword($def['password']);
            }
            if (array_key_exists('options', $def)) {
                $this->setOptions($def['options']);
            }
            if (array_key_exists('defaultTableClass', $def)) {
                $this->setDefaultTableClass($def['defaultTableClass']);
            }
            if (array_key_exists('defaultColumnClass', $def)) {
                $this->setDefaultColumnClass($def['defaultColumnClass']);
            }
            if (array_key_exists('defaultRowClass', $def)) {
                $this->setDefaultRowClass($def['defaultRowClass']);
            }
            if (array_key_exists('autoTableClassNamespace', $def)) {
                $this->setAutoTableClassNamespace($def['autoTableClassNamespace']);
            }
            if (array_key_exists('autoColumnClassNamespace', $def)) {
                $this->setAutoColumnClassNamespace($def['autoColumnClassNamespace']);
            }
            if (array_key_exists('autoRowClassNamespace', $def)) {
                $this->setAutoRowClassNamespace($def['autoRowClassNamespace']);
            }
            if (array_key_exists('regexpTableClasses', $def)) {
                $this->setRegexpTableClasses($def['regexpTableClasses']);
            }
            if (array_key_exists('regexpColumnClasses', $def)) {
                $this->setRegexpColumnClasses($def['regexpColumnClasses']);
            }
            if (array_key_exists('regexpRowClasses', $def)) {
                $this->setRegexpRowClasses($def['regexpRowClasses']);
            }
            if (array_key_exists('tableClasses', $def)) {
                $this->setTableClasses($def['tableClasses']);
            }
            if (array_key_exists('columnClasses', $def)) {
                $this->setColumnClasses($def['columnClasses']);
            }
            if (array_key_exists('rowClasses', $def)) {
                $this->setRowClasses($def['rowClasses']);
            }
            if (array_key_exists('changeHandler', $def)) {
                $this->setChangeHandler($def['changeHandler']);
            }
            $this->ready();
        }
    }

    /**
     * To be called when the object is fully configured.  Called automatically
     * from the constructor when configuring via an array specification; needs
     * to be called explicitly when configuring in fluent mode.
     *
     * @throws Thaumatic\Junxa\Exceptions\JunxaConfigurationException if the object's configuration is invalid
     * @return $this
     */
    public function ready()
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
     * @return $this
     */
    public function setHostname($val)
    {
        $this->hostname = $val;
        return $this;
    }

    /**
     * Retrieves the hostname we connect to MySQL on.
     *
     * @return string
     */
    public function getHostname()
    {
        return $this->hostname;
    }

    /**
     * Sets the database to connect to.
     *
     * @return $this
     */
    public function setDatabase($val)
    {
        $this->database = $val;
        return $this;
    }

    /**
     * Retrieves the database we connect to.
     *
     * @return string
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Sets the username to use to connect to the database.
     *
     * @return $this
     */
    public function setUsername($val)
    {
        $this->username = $val;
        return $this;
    }

    /**
     * Retrieves the username to use to connect to the database.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Sets the password to use to connect to the database.
     *
     * @return $this
     */
    public function setPassword($val)
    {
        $this->password = $val;
        return $this;
    }

    /**
     * Retrieves the password to use to connect to the database.
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
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
     * Sets the options bitmask for the database.
     *
     * @param int bitmask of Thaumatic\Junxa::DB_* values
     * @return $this
     */
    public function setOptions($val)
    {
        $this->options = $val;
        return $this;
    }

    /**
     * Retrieves the options bitmask for the database.
     *
     * @return int bitmask of Thaumatic\Junxa::DB_* values
     */
    public function getOptions($val)
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
    public function setOption($option, $flag)
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
    public function getOption($option)
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
    public function setDefaultTableClass($val)
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
    public function getDefaultTableClass()
    {
        return $this->defaultTableClass;
    }

    /**
     * Sets the class to use as the row model when no more specific row model
     * is found.
     *
     * @param string class name
     * @return $this
     */
    public function setDefaultRowClass($val)
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
    public function getDefaultRowClass()
    {
        return $this->defaultRowClass;
    }

    /**
     * Sets the namespace that will be searched for a class to use for the
     * table model if no more specific table model is found (looking for a
     * class name that is the PascalCase version of the table name).
     *
     * @param string namespace prefix, without trailing backslash
     * @return $this
     */
    public function setAutoTableClassNamespace($val)
    {
        $this->autoTableClassNamespace = $val;
        return $this;
    }

    /**
     * Retrieves the auto table class namespace.
     *
     * @return string
     */
    public function getAutoTableClassNamespace($val)
    {
        return $this->autoTableClassNamespace;
    }

    /**
     * Sets the namespace that will be searched for a class to use for the
     * column model if no more specific column model is found (looking for a
     * class name that is the PascalCase version of the table name).
     *
     * @param string namespace prefix, without trailing backslash
     * @return $this
     */
    public function setAutoColumnClassNamespace($val)
    {
        $this->autoColumnClassNamespace = $val;
        return $this;
    }

    /**
     * Retrieves the auto column class namespace.
     *
     * @return string
     */
    public function getAutoColumnClassNamespace($val)
    {
        return $this->autoColumnClassNamespace;
    }

    /**
     * Sets the namespace that will be searched for a class to use for the
     * row model if no more specific row model is found (looking for a
     * class name that is the PascalCase version of the table name).
     *
     * @param string namespace prefix, without trailing backslash
     * @return $this
     */
    public function setAutoRowClassNamespace($val)
    {
        $this->autoRowClassNamespace = $val;
        return $this;
    }

    /**
     * Retrieves the auto row class namespace.
     *
     * @return string
     */
    public function getAutoRowClassNamespace($val)
    {
        return $this->autoRowClassNamespace;
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
    public function setRegexpTableClasses(array $val)
    {
        $this->regexpTableClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of regexp-based table classes.
     *
     * @return array<string:string>
     */
    public function getRegexpTableClasses()
    {
        return $this->regexpTableClasses;
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
    public function setRegexpColumnClasses(array $val)
    {
        $this->regexpColumnClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of regexp-based column classes.
     *
     * @return array<string:string>
     */
    public function getRegexpColumnClasses()
    {
        return $this->regexpColumnClasses;
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
    public function setRegexpRowClasses(array $val)
    {
        $this->regexpRowClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of regexp-based row classes.
     *
     * @return array<string:string>
     */
    public function getRegexpRowClasses()
    {
        return $this->regexpRowClasses;
    }

    /**
     * Sets a mapping of table names to class names.  If a table name appears
     * in this mapping, the corresponding class name will be used for the
     * table model.  This is the most specific table class specification.
     *
     * @param array<string:string> map of table names to class names
     * @return $this
     */
    public function setTableClasses(array $val)
    {
        $this->tableClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of explicit table classes.
     *
     * @return array<string:string>
     */
    public function getTableClasses()
    {
        return $this->tableClasses;
    }

    /**
     * Sets a mapping of table names to class names.  If a table name appears
     * in this mapping, the corresponding class name will be used for the
     * column model.  This is the most specific column class specification.
     *
     * @param array<string:string> map of table names to class names
     * @return $this
     */
    public function setColumnClasses(array $val)
    {
        $this->columnClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of explicit column classes.
     *
     * @return array<string:string>
     */
    public function getColumnClasses()
    {
        return $this->columnClasses;
    }

    /**
     * Sets a mapping of table names to class names.  If a table name appears
     * in this mapping, the corresponding class name will be used for the
     * row model.  This is the most specific row class specification.
     *
     * @param array<string:string> map of table names to class names
     * @return $this
     */
    public function setRowClasses(array $val)
    {
        $this->rowClasses = $val;
        return $this;
    }

    /**
     * Retrieves the mapping of explicit row classes.
     *
     * @return array<string:string>
     */
    public function getRowClasses()
    {
        return $this->rowClasses;
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
    public function setChangeHandler($val)
    {
        $this->changeHandler = $val;
        return $this;
    }

    /**
     * Retrieves the change handler configuration.
     *
     * @return Thaumatic\Junxa|array
     */
    public function getChangeHandler()
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
    public function getChangeHandlerObject()
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
                $class = empty($def['class']) ? 'Thaumatic\Junxa' : $def['class'];
                $this->changeHandlerObject = new $class($def);
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
    public function connect()
    {
        if (!$this->database) {
            throw new JunxaConfigurationException('database to connect to has not been specified');
        }
        if ($this->getOption(self::DB_PERSISTENT_CONNECTION)) {
            $this->link = new \mysqli('p:' . $this->hostname, $this->username, $this->password, $this->database);
        } else {
            $this->link = new \mysqli($this->hostname, $this->username, $this->password, $this->database);
        }
        return $this;
    }

    /**
     * Retrieves the class to use as the table model for a given table.
     *
     * @param string the table name
     * @return string
     */
    public function tableClass($table)
    {
        if (!empty($this->tableClasses[$table])) {
            return $this->tableClasses[$table];
        }
        foreach ($this->regexpTableClasses as $name => $class) {
            if (preg_match($name, $table)) {
                return $class;
            }
        }
        if (!empty($this->autoTableClassNamespace)) {
            $name = $this->autoTableClassNamespace . '\\' . self::pascalCase($table);
            if (class_exists($name)) {
                return $name;
            }
        }
        if (!empty($this->defaultTableClass)) {
            return $this->defaultTableClass;
        }
        return 'Thaumatic\Junxa\Table';
    }

    /**
     * Retrieves the class to use as the column model for a given table.
     *
     * @param string the table name
     * @return string
     */
    public function columnClass($table)
    {
        if (!empty($this->columnClasses[$table])) {
            return $this->columnClasses[$table];
        }
        foreach ($this->regexpColumnClasses as $name => $class) {
            if (preg_match($name, $table)) {
                return $class;
            }
        }
        if (!empty($this->autoColumnClassNamespace)) {
            $name = $this->autoColumnClassNamespace . '\\' . self::pascalCase($table);
            if (class_exists($name)) {
                return $name;
            }
        }
        if (!empty($this->defaultColumnClass)) {
            return $this->defaultColumnClass;
        }
        return 'Thaumatic\Junxa\Column';
    }

    /**
     * Retrieves the class to use as the row model for a given table.
     *
     * @param string the table name
     * @return string
     */
    public function rowClass($table)
    {
        if (!empty($this->rowClasses[$table])) {
            return $this->rowClasses[$table];
        }
        foreach ($this->regexpRowClasses as $name => $class) {
            if (preg_match($name, $table)) {
                return $class;
            }
        }
        if (!empty($this->autoRowClassNamespace)) {
            $name = $this->autoRowClassNamespace . '\\' . self::pascalCase($table);
            if (class_exists($name)) {
                return $name;
            }
        }
        if (!empty($this->defaultRowClass)) {
            return $this->defaultRowClass;
        }
        return 'Thaumatic\Junxa\Row';
    }

    /**
     * Retrieves the names of the tables attached to this database.
     *
     * @return array<string>
     */
    public function tables()
    {
        return $this->tables;
    }

    /**
     * Retrieves whether a table with the specified name is attached to this database.
     *
     * @param string the table name
     * @return bool
     */
    public function tableExists($table)
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
    public function table($name)
    {
        if (empty($this->tableModels[$name])) {
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
     * Loads one or more tables onto the database model, each specified as an argument.
     *
     * @param string... the names of tables to load
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException if a table specified does not exist
     */
    public function loadTables()
    {
        $baseTables = func_get_args();
        if (count($baseTables) === 1 && is_array($baseTables[0])) {
            $baseTables = $baseTables[0];
        }
        $tables = [];
        foreach ($baseTables as $baseTable) {
            if (empty($this->tableModels[$baseTable])) {
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
    }

    public function reportQueryStatistics()
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

    public function query($query = null, $mode = 0, $echo = false, $emptyOkay = false)
    {
        if ($query === null) {
            return QueryBuilder::make($this);
        }
        $isResult = false;
        $this->queryStatus = self::RESULT_FAILURE;
        $this->queryMessage = '';
        $insertIgnore = false;
        $update = false;
        $whyEcho = $echo ? 'function parameter' : null;
        $errorOkay = false;
        switch (gettype($query)) {
            case 'string':
                if (preg_match('/^\s*(SELECT|SHOW)\s*/is', $query)) {
                    $isResult = true;
                } else {
                    $handler = $this->getChangeHandlerObject();
                    if ($handler) {
                        $result = $handler->query($query, $mode, $echo, $emptyOkay);
                        $this->queryStatus = $handler->getQueryStatus();
                        $this->queryMessage = $handler->getQueryMessage();
                        $this->insertId = $handler->getInsertId();
                        return $result;
                    } else {
                        if (preg_match('/^\s*UPDATE\s+/is', $query)) {
                            $update = true;
                        } elseif (preg_match('/^\s*INSERT[^(]+IGNORE\s+/is', $query)) {
                            $insertIgnore = true;
                        }
                    }
                }
                break;
            case 'array':
                $query = new QueryBuilder($query);
                // fallthrough
            case 'object':
                if (!($query instanceof QueryBuilder)) {
                } new JunxaInvalidQueryException(
                    'object query must be a '
                    . 'Thaumatic\Junxa\Query\Builder, '
                    . ' got ' . get_class($query)
                );
                $query->validate();
                if ($mode === 0) {
                    $mode = $query->getMode();
                }
                $queryType = $query->getType();
                if ($queryType === 'select' || $queryType === 'show') {
                    $isResult = true;
                } else {
                    $handler = $this->getChangeHandlerObject();
                    if ($handler) {
                        $result = $handler->query($query, $mode, $echo, $emptyOkay);
                        $this->queryStatus = $handler->queryStatus;
                        $this->queryMessage = $handler->queryMessage;
                        $this->insertId = $handler->insertId;
                        return $result;
                    }
                }
                if ($query->hasOptions()) {
                    if ($query->option('emptyOkay')) {
                        $emptyOkay = true;
                    }
                    if ($query->option('error_okay')) {
                        $errorOkay = true;
                    }
                    if (!$echo && $query->option('echo')) {
                        $echo = true;
                        $whyEcho = 'query option';
                    }
                }
                if ($queryType === 'update') {
                    $update = true;
                } elseif ($queryType === 'insert' && $query->option('ignore')) {
                    $insertIgnore = true;
                }
                $query = $query->express();
                break;
            default:
                throw new JunxaInvalidQueryException('invalid argument to query()');
        }
        if ($this->getOption(self::DB_COLLECT_QUERY_STATISTICS)) {
            $this->queryStatistics[$query]++;
            self::$overallQueryStatistics[$query]++;
        }
        if (!$echo && !empty($GLOBALS['echo'])) {
            $echo = true;
            $whyEcho = 'global flag';
        }
        if ($echo) {
            echo("SQL (echoed because of $whyEcho): $query <br />\n");
        }
        $res = $this->link->query($query);
        if ($res) {
            if ($insertIgnore && $this->getAffectedRows() <= 0) {
                $this->queryStatus = self::RESULT_INSERT_FAIL;
            } elseif ($update && $this->getAffectedRows() <= 0) {
                $this->queryStatus = self::RESULT_UPDATE_FAIL;
            } else {
                $this->queryStatus = self::RESULT_SUCCESS;
            }
        } else {
            $this->queryMessage = $this->link->error;
            $errno = $this->link->errno;
            if ($errno == 2006 || $errno == 2013) {
                usleep(1000);
                $this->connect();
                return $this->query($query, $mode, $echo, $emptyOkay);
            }
            $this->queryStatus = self::RESULT_FAILURE;
            if (!$errorOkay) {
                throw new JunxaQueryExecutionException($this->queryMessage . ' from ' . $query);
            }
        }
        if (!$isResult && preg_match('/^\s*(INSERT|REPLACE)\b/i', $query)) {
            $this->insertId = $this->link->insert_id;
        }
        if (!$mode) {
            $mode = $isResult ? self::QUERY_OBJECTS : self::QUERY_FORGET;
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

    public function determineTables()
    {
        $this->tables = [];
        $res = $this->link->query('SHOW TABLES');
        while ($row = $res->fetch_array(MYSQLI_NUM)) {
            $table = $row[0];
            $this->tables[] = $table;
        }
    }

    public function getAffectedRows()
    {
        return $this->link->affected_rows;
    }

    /**
     * Property-mode accessor for tables.
     *
     * @param string
     * @return Thaumatic\Junxa\Table
     * @throws Thaumatic\Junxa\Exceptions\JunxaNoSuchTableException if the table does not exist
     */
    public function __get($name)
    {
        return $this->table($name);
    }

    /**
     * Retrieves the result code for the last query performed.
     *
     * @var int Thaumatic\Junxa::RESULT_*
     */
    public function getQueryStatus()
    {
        return $this->queryStatus;
    }

    /**
     * Retrieves the error result for the last query performed.
     *
     * @var string
     */
    public function getQueryMessage()
    {
        return $this->queryMessage;
    }

    /**
     * Retrieves the object-specific statistical array of how many times a given query is run.
     * This is only populated by Junxa instances with Junxa::DB_COLLECT_QUERY_STATISTICS enabled.
     *
     * @return array<string:int>
     */
    public function getQueryStatistics()
    {
        return $this->queryStatistics;
    }

    /**
     * Retrieves the class-general statistical array of how many times a given query is run.
     * This is only populated by Junxa instances with Junxa::DB_COLLECT_QUERY_STATISTICS enabled.
     *
     * @return array<string:int>
     */
    public function getOverallQueryStatisticsDynamic()
    {
        return self::$overallQueryStatistics;
    }

    /**
     * Retrieves the class-general statistical array of how many times a given query is run.
     * This is only populated by Junxa instances with Junxa::DB_COLLECT_QUERY_STATISTICS enabled.
     *
     * @return array<string:int>
     */
    public static function getOverallQueryStatistics()
    {
        return self::$overallQueryStatistics;
    }

    /**
     * Retrieves the last insert ID from a database query.
     *
     * @return numeric
     */
    public function getInsertId()
    {
        return $this->insertId;
    }

    /**
     * Sets the global query echoing behavior on or off.
     */
    public static function setEcho($val)
    {
        self::$echo = $val && true;
    }

    /**
     * Retrieves the state of global query echoing.
     *
     * @return bool
     */
    public static function getEcho()
    {
        return self::$echo;
    }

    /**
     * Resolves Junxa query structures into SQL text.
     *
     * @param mixed the data to be resolved
     * @param Thaumatic\Junxa\Query\Builder the current query builder object
     * @param string the statement context in which the data is being resolved
     * @param Thaumatic\Junxa\Column the column, if any, which the data is being prepared for
     * @param Thaumatic\Junxa\Query\Builder the parent query, if any
     */
    public static function resolve($item, QueryBuilder $query, $context, $column, $parent)
    {
        if (is_array($item)) {
            $elem = [];
            $ix = 0;
            foreach ($item as $subitem) {
                $elem[$ix++] = self::resolve($subitem, $query, $context, $column, $parent);
            }
            if ($context == 'join') {
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
            return $column->represent($item, $query, $context, $parent);
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
    public static function quote($data)
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
        if (!is_string($data)) {
            throw new JunxaInvalidQueryException(
                'cannot use ' . gettype($data) . ' as raw data'
            );
        }
            $data = $link->real_escape_string($data);
            return "'" . $data . "'";
    }

    /**
     * Converts text to Pascal case.
     *
     * @param string text to convert
     * @return string
     */
    public static function pascalCase($text)
    {
        return ucfirst(
            preg_replace_callback(
                '/_([^_])/',
                function ($match) {
                    return ucfirst($match[1]);
                },
                strtolower($text)
            )
        );
    }

    /**
     * Converts text to camel case.
     *
     * @param string text to convert
     * @return string
     */
    public static function camelCase($text)
    {
        return
            preg_replace_callback(
                '/_([^_])/',
                function ($match) {
                    return ucfirst($match[1]);
                },
                strtolower($text)
            );
    }

    /**
     * Returns whether the result code passed as its argument indicates a
     * successful query.  Since there are several result codes which indicate
     * "success" along with other result information, this function should be
     * used as a general "okayness"* check.
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
    public static function OK($code)
    {
        return $code > 0;
    }
}
