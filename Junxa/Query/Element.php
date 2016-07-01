<?php

namespace Thaumatic\Junxa\Query;

use Thaumatic\Junxa;
use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Exceptions\JunxaInvalidQueryException;
use Thaumatic\Junxa\Query;
use Thaumatic\Junxa\Table;

/**
 * Abstracts any of various elements of queries that require representation not provided elsewhere -- basically
 * everything in a query that isn't a Column, an Assignment, or string/numeric data.  Mostly for internal use;
 * application developers should not normally need to interact with it explicitly.
 */
class Element
{

    private $style;
    private $type;
    private $content;
    private $id;

    public function __construct($style, $type, $args = [])
    {
        static $aliasCount;
        $this->style = $style;
        $this->type = $type;
        $this->content = is_array($args) ? $args : [$args];
        switch ($this->style) {
            case 'interleave':
                if ($this->type === '') {
                    if (count($this->content) < 1) {
                        throw new JunxaInvalidQueryException("at least one argument required for unitary $this->style element");
                    }
                } else {
                    if (count($this->content) < 2) {
                        throw new JunxaInvalidQueryException("at least two arguments required for $this->style $this->type element");
                    }
                }
                break;
            case 'equality':
            case 'inequality':
            case 'comparison':
            case 'container':
                if (count($this->content) != 2) {
                    throw new JunxaInvalidQueryException("two arguments required for $this->style $this->type element");
                }
                break;
            case 'head':
            case 'tail':
            case 'unary':
            case 'interval':
            case 'cast':
                if (count($this->content) != 1) {
                    throw new JunxaInvalidQueryException("one argument required for $this->style $this->type element");
                }
                break;
            case 'literal':
                if (count($this->content) != 0) {
                    throw new JunxaInvalidQueryException("zero arguments required for $this->style element");
                }
                break;
            case 'alias':
                $this->id = 'alias' . ++$aliasCount;
                break;
            case 'function':
            case 'join':
            case 'joincond':
                break;
            default:
                throw new JunxaInvalidQueryException("unknown style '$this->style'");
        }
    }

    public function tableScan(&$tables, &$null)
    {
        foreach ($this->content as $item) {
            if (is_object($item) && method_exists($item, 'tableScan')) {
                $item->tableScan($tables, $null);
            }
        }
        if ($this->style === 'join') {
            switch ($this->type) {
                case 'LEFT':
                case 'NATURAL LEFT':
                case 'RIGHT':
                case 'NATURAL RIGHT':
                    if ($this->content[0] instanceof Table) {
                        $null[$this->content[0]->getName()] = true;
                    }
                    break;
            }
        }
    }

    public function nullCheck($what, &$null)
    {
        if (is_array($what)) {
            for ($i = 0; $i < count($what); $i++) {
                $this->nullCheck($what[$i], $null);
            }
        } elseif ($what instanceof Table) {
            $null[$what->getName()] = true;
        } elseif ($what instanceof Element && $what->getStyle() === 'join') {
            $what->nullAllTables($null);
        }
    }

    public function nullAllTables(&$null)
    {
        foreach ($this->content as $item) {
            $this->nullCheck($item, $null);
        }
    }

    public function useNullSafeEquivalence($query, $context, $base1, $base2, $value1, $value2)
    {
        if ($value1 === 'NULL' || $value2 === 'NULL') {
            return true;
        }
        if (is_object($base1)) {
            if (!($base1 instanceof Column) || $base1->contextNull($query, $context)) {
                return true;
            }
        }
        if (is_object($base2)) {
            if (!($base2 instanceof Column) || $base2->contextNull($query, $context)) {
                return true;
            }
        }
        return false;
    }

    public function express($query, $context, $column, $parent)
    {
        $base = $this->content;
        $refColumn = $column;
        $type = $this->type;
        switch ($this->style) {
            case 'comparison':
                $col0 = $base[0] instanceof Column;
                $col1 = $base[1] instanceof Column;
                if ($col0 && !$col1) {
                    $refColumn = $base[0];
                } elseif ($col1 && !$col0) {
                    $refColumn = $base[1];
                }
                break;
            case 'function':
                $context = 'function';
                $refColumn = null;
                break;
        }
        $values = [];
        for ($i = 0; $i < count($base); $i++) {
            $values[$i] = Junxa::resolve($base[$i], $query, $context, $refColumn, $this);
        }
        switch ($this->style) {
            case 'interleave':
                $out = '(' . join(' ' . $type . ' ', $values) . ')';
                break;
            case 'function':
                $out = $type . '(' . join(', ', $values) . ')';
                break;
            case 'cast':
                $out = 'CAST(' . $values[0] . ' AS ' . $this->type . ')';
                break;
            case 'comparison':
                switch ($type) {
                    case '=':
                        if ($this->useNullSafeEquivalence($query, $context, $base[0], $base[1], $values[0], $values[1])) {
                            $type = '<=>';
                        }
                        break;
                    case '!=':
                        if ($base[1] === null) {
                            $type = 'IS NOT';
                        } elseif ($base[0] === null) {
                            $type = 'IS NOT';
                            list($values[1], $values[0]) = $values;
                        }
                        break;
                }
                $out = '(' . $values[0] . ' ' . $type . ' ' . $values[1] . ')';
                break;
            case 'unary':
                $out = '(' . $type . ' ' . $values[0] . ')';
                break;
            case 'head':
                $out = $type . ' ' . $values[0];
                break;
            case 'tail':
                $out = $values[0] . ' ' . $type;
                break;
            case 'literal':
                $out = $type;
                break;
            case 'container':
                $out = $values[0] . ' ' . $type . ' (' . $values[1] . ')';
                break;
            case 'joincond':
                $out = $type . ' (' . join(', ', $values) . ')';
                break;
            case 'join':
                $out = "\n\t" . $type . ' JOIN ' . $values[0];
                break;
            case 'alias':
                if ($context != 'join' && $base[0] instanceof Table) {
                    $out = $values[0];
                } elseif (!empty($query->expressed[$this->id]) && $context != 'select' && $context != 'where' && $context != 'join') {
                    $out = '`' . $base[1] . '`';
                } else {
                    $out = $values[0];
                    if (empty($query->expressed[$this->id]) && $parent instanceof Query && ($context == 'select' || $context == 'join')) {
                        $out .= ' ' . $type . ' `' . $base[1] . '`';
                    }
                }
                    $query->expressed[$this->id] = true;
                break;
            case 'interval':
                $out = 'INTERVAL ' . $values[0] . ' ' . $type;
                break;
            default:
                throw new JunxaInvalidQueryException("unknown element, style '$this->style' type '$this->type'");
        }
        return $out;
    }
}
