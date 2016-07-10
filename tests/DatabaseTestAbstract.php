<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;

abstract class DatabaseTestAbstract extends \PHPUnit_Framework_TestCase
{

    const TEST_DATABASE_NAME = 'test_junxa';
    const TEST_DATABASE_SETUP_FILE_NAME = 'test.sql';

    /**
     * @var mysqli link directly to the database, used for creating and
     * tearing down the test database
     */
    private static $rawLink;

    /**
     * @var Thaumatic\Junxa Junxa model of the test database
     */
    private static $db;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$rawLink = new \mysqli('localhost');
        if (self::$rawLink->connect_error) {
            throw new Exception(
                'need anonymous access to mysql on localhost to '
                . 'run database-based tests'
            );
        }
        $filename = __DIR__ . DIRECTORY_SEPARATOR . self::TEST_DATABASE_SETUP_FILE_NAME;
        if (!is_readable($filename)) {
            throw new Exception($filename . ' missing');
        }
        $res = self::$rawLink->query('CREATE DATABASE `' . self::TEST_DATABASE_NAME . '`');
        self::$rawLink->select_db(self::TEST_DATABASE_NAME);
        $res = self::$rawLink->multi_query(file_get_contents($filename));
        self::$rawLink->store_result();
        while (self::$rawLink->more_results()) {
            self::$rawLink->next_result();
        }
        self::$db = Junxa::make()
            ->setHostname('localhost')
            ->setDatabase(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->ready()
        ;
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        self::$rawLink->query('DROP DATABASE `' . self::TEST_DATABASE_NAME . '`');
    }

    public function db()
    {
        return self::$db;
    }

    public function rawLink()
    {
        return self::$rawLink;
    }

}
