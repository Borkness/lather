<?php

namespace Lather\Macros;

trait Filterable
{
    /**
     * Macro for filtering based on a where clause.
     *
     * @param string $key
     * @param string $check
     * @param string $value
     */
    public function macroWhere($key, $check, $value)
    {
        return array_filter($this->formattedResponse, function ($v, $k) use ($key, $value, $check) {
            if ($k == $key) {
                switch ($check) {
                    case '=':
                        return $v == $value;
                        break;
                    case '<':
                        return $v < $value;
                        break;
                    case '>':
                        return $v > $value;
                        break;
                    case '<=':
                        return $v <= $value;
                        break;
                    case '>=':
                        return $v >= $value;
                        break;
                    case '!=':
                        return $v != $value;
                    default:
                        return false;
                        break;
                }
            } else {
                return false;
            }
        }, ARRAY_FILTER_USE_BOTH);
    }
}
