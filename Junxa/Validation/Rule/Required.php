<?php

namespace Thaumatic\Junxa\Validation\Rule;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Validation\RuleAbstract;

/**
 * Validation rule for required fields.
 */
class Required extends RuleAbstract
{

    /**
     * {@inheritdoc}
     */
    public function getFailureMessageFormat()
    {
        return '%%NAME_WITH_ARTICLE%% is required';
    }

    /**
     * {@inheritdoc}
     */
    public function validate(Column $column, &$value, $data, $prefix, $suffix)
    {
        return $value != '';
    }

    /**
     * {@inheritdoc}
     */
    public function getJavascriptFailureConditions(Column $column, $refer, $args = [])
    {
        return [
            $refer . '.value == \'\'',
        ];
    }

}
