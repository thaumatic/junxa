<?php

namespace Thaumatic\Junxa\Validation\Rule;

use Thaumatic\Junxa\Column;
use Thaumatic\Junxa\Validation\RuleAbstract;

/**
 * Validation rule for money fields that may be left blank.
 */
class MoneyIfSet extends RuleAbstract
{

    /**
     * {@inheritdoc}
     */
    public function getFailureMessageFormat()
    {
        return '%%NAME%% must be a monetary amount or empty';
    }

    /**
     * {@inheritdoc}
     */
    public function validate(Column $column, &$value, $data, $prefix, $suffix)
    {
        if ($value === '' || $value === null) {
            return true;
        } else {
            $value = preg_replace('/^\p{Sc}+/', '', $value);
            return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getJavascriptProcessing(Column $column, $refer, $options = [])
    {
        return [
            $refer
            . '.value = '
            . $refer
            . '.value.replace(/^[$¢£¤¥?????????\u20a0-\u20bd\ua838\ufdfc\ufe69\uff04\uffe0\uffe1\uffe5\uffe6]+/, \'\')',
        ];
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
