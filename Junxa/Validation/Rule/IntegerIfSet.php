<?php

namespace Thaumatic\Junxa\Validation\Rule;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Validation\RuleAbstract;

/**
 * Validation rule for integer fields that may be left unset.
 */
class IntegerIfSet extends RuleAbstract
{

    /**
     * {@inheritdoc}
     */
    public function getFailureMessageFormat()
    {
        return '%%NAME%% must be a number (with no decimals) or empty';
    }

    /**
     * {@inheritdoc}
     */
    public function validate(Column $column, &$value, $data, $prefix, $suffix)
    {
        return $value === '' || $value === null || strval(intval($value)) === $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getJavascriptFailureConditions(Column $column, $refer, $args = [])
    {
        return [
            $refer . '.value !== \'\' && Number.parseInt(' . $refer . '.value) == ' . $refer . '.value',
        ];
    }

}
