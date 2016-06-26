<?php

abstract class DatabaseTest
    extends PHPUnit_Framework_TestCase
{

    const TEST_DATABASE_NAME = 'test_junxa';
    const TEST_DATABASE_SETUP_FILE_NAME = 'test.sql';

    private static $db;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$db = new mysqli('localhost');
        if(self::$db->connect_error) {
            throw new Exception(
                'need anonymous access to mysql on localhost to '
                . 'run database-based tests'
            );
        }
        $filename = __DIR__ . DIRECTORY_SEPARATOR . self::TEST_DATABASE_SETUP_FILE_NAME;
        if (!is_readable($filename)) {
            throw new Exception($filename . ' missing');
        }
        $res = self::$db->query('CREATE DATABASE `' . self::TEST_DATABASE_NAME . '`');
        self::$db->select_db(self::TEST_DATABASE_NAME);
        $res = self::$db->multi_query(file_get_contents($filename));
        self::$db->store_result();
        while(self::$db->more_results())
            self::$db->next_result();
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        self::$db->query('DROP DATABASE `' . self::TEST_DATABASE_NAME . '`');
    }

    public function db()
    {
        return self::$db;
    }

}
