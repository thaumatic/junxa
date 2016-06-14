<?php

namespace Thaumatic\Junxa;

/**
 * Models a database column.
 */
class Column
{

	private $default;
	private $dynalias;
	private $flags;
	private $fullType;
	private $length;
	private $name;
	private $precision;
	private $table;
	private $type;
	private $typeClass;
	private $values;

	public function __construct($table, $name, $info, $flags, $colinfo, $dynalias)
	{
		$this->table = $table;
		$this->name = $name;
		$this->dynalias = $dynalias;
		$this->type = $info->type;
		$this->length = ($info->max_length === null) ? null : intval($info->max_length);
		$this->flags = array();
		$this->flags['null'] = !$info->not_null;
		foreach(array('numeric', 'unsigned', 'zerofill', 'blob') as $flag)
			if($info->$flag)
				$this->flags[$flag] = true;
		foreach(preg_split('/\s+/', $flags) as $flag) {
			switch($flag) {
			case 'primary_key'      :
				$this->flags['primary'] = true;
				$this->flags['unique'] = true;
				$this->flags['key'] = true;
				break;
			case 'unique_key'       :
				$this->flags['unique'] = true;
				$this->flags['key'] = true;
				break;
			case 'multiple_key'     :
				$this->flags['key'] = true;
				break;
			case 'auto_increment'   :
				$this->flags['auto_increment'] = true;
				break;
			case 'enum'             :
				$this->flags['enum'] = true;
				break;
			case 'set'              :
				$this->flags['set'] = true;
				break;
			case 'timestamp'        :
				$this->flags['timestamp'] = true;
				break;
			case 'binary'           :
				$this->flags['binary'] = true;
				break;
			}
		}
		$this->default = $info->def;
		if($colinfo) {
			$this->default = $colinfo->Default;
			$this->fullType = $colinfo->Type;
			if($colinfo->Null == 'YES')
				$this->flags['null'] = true;
			if(preg_match('/^([^\s\(]+)/', $this->fullType, $match))
				$this->type = $match[1];
			if(!empty($this->flags['enum']) || !empty($this->flags['set'])) {
				preg_match('/\(.*\)$/', $this->fullType, $match);
				$list = substr($match[0], 2, strlen($match[0]) - 4);
				$this->values = preg_split("/','/", $list);
				for($i = 0; $i < count($this->values); $i++)
					$this->values[$i] = preg_replace("/''/", "'", $this->values[$i]);
				if(!empty($this->flags['null']) && !empty($this->flags['enum']))
					array_unshift($this->values, null);
			} else {
				if(preg_match("/\((\d+),(\d+)\)$/", $this->fullType, $match)) {
					$this->length = intval($match[1]);
					$this->precision = intval($match[2]);
				} elseif(preg_match("/\((\d+)\)$/", $this->fullType, $match)) {
					$this->length = intval($match[1]);
				}
			}
		}
		switch($this->type) {
		case 'tinyint'      :
		case 'smallint'     :
		case 'mediumint'    :
		case 'int'          :
		case 'bigint'       :
		case 'year'         :
			$this->typeClass = 'int';
			break;
		case 'float'        :
		case 'double'       :
		case 'numeric'      :
		case 'decimal'      :
			$this->typeClass = 'float';
			break;
		case 'datetime'     :
		case 'timestamp'    :
			$this->typeClass = 'datetime';
			break;
		case 'date'         :
			$this->typeClass = 'date';
			break;
		case 'time'         :
			$this->typeClass = 'time';
			break;
		case 'set'          :
			$this->typeClass = 'array';
			break;
		default             :
			$this->typeClass = 'text';
			break;
		}
		if(method_exists($this, 'init'))
			$this->init();
	}

	public function db()
	{
		return $this->table->db();
	}

	public function table()
	{
		return $this->table;
	}

	public function name()
	{
		return $this->name;
	}

	public function isDynamic()
	{
		return $this->dynalias ? true : false;
	}

	public function dynamicInfo()
	{
		return $this->dynalias;
	}

	public function contextNull($query, $context)
	{
		if(!empty($this->flags['null']))
			return true;
		if($context !== 'join' && $query && !empty($query->isNullTable($this->table->getName())))
			return true;
		return false;
	}

	public function represent($value, $query, $context, $parent)
	{
		if(!isset($value) && $this->contextNull($query, $context))
			return 'NULL';
		switch($this->typeClass) {
		case 'text'     :
		case 'date'     :
		case 'datetime' :
		case 'time'     :
			return "'" . $value . "'";
		case 'array'    :
			return "'" . join(',', $value) . "'";
		case 'int'      :
			switch($this->type) {
			case 'bigint'   :
				return preg_replace('/[^0-9-]+/', '', $value);
			case 'int'      :
				if(!empty($this->flags['zerofill']))
					return preg_replace('/[^0-9-]+/', '', $value);
				if(!empty($this->flags['unsigned']))
					return preg_replace('/\D+/', '', $value);
				return intval($value);
			case 'tinyint'  :
				if(!empty($this->flags['zerofill']))
					return preg_replace('/[^0-9-]+/', '', $value);
				return intval($value);
			default         :
				if(!empty($this->flags['zerofill']))
					return preg_replace('/[^0-9-]+/', '', $value);
				return intval($value);
			}
			break;
		case 'float'    :
			return doubleval($value);
		default         :
			throw new \Exception('unknown type class ' . $this->typeClass);
		}
	}

	public function import($value)
	{
		if(($value === null) && !empty($this->flags['null']))
			return null;
		switch($this->typeClass) {
		case 'int'          :
			switch($this->type) {
			case 'bigint'   :
				break;
			case 'int'      :
				if(!empty($this->flags['zerofill']))
					break;
				if(!empty($this->flags['unsigned']))
					break;
				return intval($value);
			case 'tinyint'  :
				if(!empty($this->flags['zerofill']))
					break;
				if($this->length === 1 && empty($this->flags['unsigned']))
					if($value == '1')
						return true;
					elseif($value == '0')
						return false;
				return intval($value);
			default         :
				if(!empty($this->flags['zerofill']))
					break;
				return intval($value);
			}
			break;
		case 'array'        :
			return explode(',', $value);
		}
		return $value;
	}

	public function reportType()
	{
		$out = $this->type;
		if(isset($this->length))
			$out .= ', length ' . $this->length;
		if(count($this->flags)) {
			$out .= ', flags';
			foreach($this->flags as $flag => $set)
				$out .= ' ' . $flag;
		}
		if(isset($this->values)) {
			$out .= ', values ';
			foreach($this->values as $value)
				$out .= ' ' . $this->represent($value, $null, 'select');
		}
		if(isset($this->default))
			$out .= ', default ' . $this->represent($this->default, $null, 'select');
		return $out;
	}

	public function table_scan(&$tables, &$null)
	{
		$tables[$this->table->name()] = true;
	}

	public function express($query, $context, $column, $parent)
	{
		if($this->dynalias)
			return $this->dynalias->express($query, $context, $column, $parent);
		elseif(!empty($query->flags['multitable']))
			return '`' . $this->table->name() . '`.`' . $this->name() . '`';
		else
			return '`' . $this->name() . '`';
	}

	public function serialize()
	{
		$table = $this->table();
		return 'column:' . $table->name() . "\0" . $this->name();
	}

	public function setDemandOnly($flag)
	{
		$table = $this->table();
		$table->setColumnDemandOnly($this->name(), $flag);
	}

	public function queryDemandOnly()
	{
		$table = $this->table();
		return $table->queryColumnDemandOnly($this->name());
	}

}
