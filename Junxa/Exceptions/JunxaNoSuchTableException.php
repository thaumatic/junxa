<?php

namespace Thaumatic\Junxa\Exceptions;

/**
 * Exception class for requests for a nonexistent table.
 */
class JunxaNoSuchTableException
	extends JunxaException
{

	private $tableName;

	public function __construct($tableName)
	{
		$this->tableName = $tableName;
		parent::__construct('no such table: ' . $tableName);
	}

	public function getTableName()
	{
		return $this->tableName;
	}

}
