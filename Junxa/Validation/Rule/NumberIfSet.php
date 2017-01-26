<?php

namespace Thaumatic\Junxa\Validation\Rule;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Validation\RuleAbstract;

/**
 * Validation rule for numeric fields that may be left unset.
 */
class Number extends RuleAbstract
{

    /**
     * {@inheritdoc}
     */
    public function getFailureMessageFormat()
    {
        return '%%NAME%% must be a number or empty';
    }

    /**
     * {@inheritdoc}
     */
    public function validate(Column $column, &$value, $data, $prefix, $suffix)
    {
        return $value === '' || $value === null || filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function getJavascriptFailureConditions(Column $column, $refer, $args = [])
    {
        return [
            $refer
            . '.value !== \'\' && (isNaN(Number.parseFloat('
            . $refer
            . '.value)) || !Number.isFinite('
            . $refer
            . '.value))',
        ];
    }

}
