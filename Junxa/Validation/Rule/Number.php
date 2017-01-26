<?php

namespace Thaumatic\Junxa\Validation\Rule;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Validation\RuleAbstract;

/**
 * Validation rule for numeric fields.
 */
class Number extends RuleAbstract
{

    /**
     * {@inheritdoc}
     */
    public function getFailureMessageFormat()
    {
        return '%%NAME%% must be a number';
    }

    /**
     * {@inheritdoc}
     */
    public function validate(Column $column, &$value, $data, $prefix, $suffix)
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function getJavascriptFailureConditions(Column $column, $refer, $args = [])
    {
        return [
            'isNaN(Number.parseFloat(' . $refer . '.value)) || !Number.isFinite(' . $refer . '.value)',
        ];
    }

}
