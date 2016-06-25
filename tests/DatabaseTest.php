<?php

abstract class DatabaseTest
    extends PHPUnit_Framework_TestCase
{

    const TEST_DATABASE_NAME = 'test_junxa';

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
        self::$db->query('CREATE DATABASE `' . self::TEST_DATABASE_NAME . '`');
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
