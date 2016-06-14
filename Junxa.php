<?php

namespace Thaumatic;

class Junxa {

    /**
     * @const int database-level behavioral option: load all tables at initialization
     */
    const DB_PRELOAD_TABLES             = 0x00000001;

    /**
     * @const int database-level behavioral option: throw exceptions when encountering database errors
     */
    const DB_DATABASE_ERRORS            = 0x00000002;

    /**
     * @const int database-level behavioral option: cache row results from tables
     */
    const DB_CACHE_TABLE_ROWS           = 0x00000004;

    /**
     * @const int database-level behavioral option: collect statistics on queries executed
     */
    const DB_COLLECT_QUERY_STATISTICS   = 0x00000008;

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
     * @const int query output type: return results in numerically-indexed arrays
     */
    const QUERY_ARRAYS                  = 4;

    /**
     * @const int query output type: return results in stdClass objects
     */
    const QUERY_OBJECTS                 = 5;

    /**
     * @const int query output type: return results in a single associative array
     */
    const QUERY_SINGLE_ASSOC            = 6;

    /**
     * @const int query output type: return results in a single numerically-indexed array
     */
    const QUERY_SINGLE_ARRAY            = 7;

    /**
     * @const int query output type: return results in a single stdClass object
     */
    const QUERY_SINGLE_OBJECT           = 8;

    /**
     * @const int query output type: return results in a single scalar value
     */
    const QUERY_SINGLE_CELL             = 9;

    /**
     * @const int query output type: return results in an associative array mapping results of a two-column query
     */
    const QUERY_COLUMN_ASSOC            = 10;

    /**
     * @const int query output type: return results in a numerically-indexed array containing results of a single-column query
     */
    const QUERY_COLUMN_ARRAY            = 11;

    /**
     * @const int query output type: return results in a stdClass object mapping results of a two-column query
     */
    const QUERY_COLUMN_OBJECT           = 12;

    /**
     * @const int query result code: absolutely everything went perfectly with the query
     */
    const RESULT_SUCCESS                = 1;

    /**
     * @const int query result code: a table row could not be refreshed with its current content from the database
     *
     * A table row refresh is automatically called for after an table row insert.  The refresh will fail if the table
     * does not have a primary key or if the primary key is not auto_increment and does not have a value in the table
     * row object (applies to any part of a multipart primary key).
     */
    const RESULT_REFRESH_FAIL           = 2;

    /**
     * @const int query result code: a table row update was called for but no changes had been made to the table row's data
     */
    const RESULT_UPDATE_NOOP            = 3;

    /**
     * @const int query result code: a table row find was called for and more than one matching row was found, resulting in
     * the first row being used.
     */
    const RESULT_FIND_EXCESS            = 4;

    /**
     * @const int query result code: the database reports an error
     */
    const RESULT_FAILURE                = -1;

    /**
     * @const int query result code: a table row insert was called for but the table row had no values set
     */
    const RESULT_INSERT_NOOP            = -2;

    /**
     * @const int query result code: a table row replace was called for but the table row had no values set
     */
    const RESULT_REPLACE_NOOP           = -3;

    /**
     * @const int query result code: a table row update was called for but the row did not have the primary
     * key information necessary to automatically generate an update
     */
    const RESULT_UPDATE_NOKEY           = -4;

    /**
     * @const int query result code: a table row delete was called for but the row did not have the primary
     * key information necessary to automatically generate a delete
     */
    const RESULT_DELETE_FAIL            = -5;

    /**
     * @const int query result code: a table row find was called for and no matching rows were found
     */
    const RESULT_FIND_FAIL              = -6;

    /**
     * @const int query result code: an INSERT IGNORE query was executed and no rows were affected
     */
    const RESULT_INSERT_FAIL            = -7;

    /**
     * @const int query result code: an UPDATE query affected no rows
     */
    const RESULT_UPDATE_FAIL            = -8;

}
