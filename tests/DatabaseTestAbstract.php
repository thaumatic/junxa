<?php

namespace Thaumatic\Junxa\Tests;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;

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
     * @var array<Thaumatic\Junxa\Row> array of rows that were generated
     * by testing and may need to be deleted afterward
     */
    private $generatedRows = [];

    /**
     * @var Thaumatic\Junxa Junxa model of the test database, class reference
     */
    private static $sharedDb;

    /**
     * @var Thaumatic\Junxa Junxa model of the test database, object reference
     */
    protected static $db;

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
        self::$sharedDb = Junxa::make()
            ->setHostname('localhost')
            ->setDatabaseName(DatabaseTestAbstract::TEST_DATABASE_NAME)
            ->setForeignKeySuffix('Id')
            ->ready()
        ;
    }

    public function setUp()
    {
        parent::setUp();
        $this->db = self::$sharedDb;
    }

    public function tearDown()
    {
        parent::tearDown();
        $toThrow = null;
        foreach ($this->generatedRows as $row) {
            if (!$row->getPrimaryKeyUnset() && !$row->getDeleted()) {
                try {
                    $row->delete();
                } catch (JunxaInvalidQueryException $e) {
                    if (!$toThrow) {
                        $toThrow = $e;
                    }
                }
            }
        }
        if ($toThrow) {
            throw $toThrow;
        }
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        self::$rawLink->query('DROP DATABASE `' . self::TEST_DATABASE_NAME . '`');
    }

    protected function addGeneratedRow($row)
    {
        $this->generatedRows[] = $row;
    }

}
