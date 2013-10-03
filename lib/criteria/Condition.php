<?php
namespace ActiveRecord\Criteria;

class Condition {
    protected $parent, $field;
    public function __construct($criteria, $field) {
        $this->parent = $criteria;
        $this->field = $field;
    }
    
    public function raw($op, $value) {
        $field = $this->field;
        return $this->parent->_raw_condition("$field $op", $field, $value);
    }
    /**
     * field = ?
     */
    public function eq($value) {
        return $this->raw('= ?', $value);
    }
    /**
     * field != ?
     */
    public function neq($value) {
        return $this->raw('!= ?', $value);
    }
    /**
     * field > ?
     */
    public function gt($value) {
        return $this->raw('> ?', $value);
    }
    /**
     * field >= ?
     */
    public function gte($value) {
        return $this->raw('>= ?', $value);
    }
    /**
     * field < ?
     */
    public function lt($value) {
        return $this->raw('< ?', $value);
    }
    /**
     * field <= ?
     */
    public function lte($value) {
        return $this->raw('<= ?', $value);
    }
    /**
     * field BETWEEN $a AND $b
     *
     * When $strict === false 
     * And one value is considered empty, it will transform to #lte or #gte:
     * empty($b) ? >= $a
     * empty($a) ? <= $b
     *
     * @params $a Start value
     * @params $b End value
     * @params bool $strict [true] If should expand or not
     */
    public function between($a, $b, $strict = true) {
        if($strict)
            return $this->raw('BETWEEN ? AND ?', array($a, $b));
        if( empty($b) )
            return $this->gte($a);
        if( empty($a) )
            return $this->lte($b);
        return $this->raw('BETWEEN ? AND ?', array($a, $b));
    }
    /**
     * If $values is only one, it's converted to `#eq`
     * @overload in($array)
     * @params Array $array with more than one element
     *      field IN(?, ?, ...)
     *
     * @overload in($mixed)
     * @params Mixed $mixed Array with one element or anything else
     *      alias of `#eq`
     */
    public function any_of(/* ... */) {
        $values = func_get_args();
        $count = count($values);
        if($count <= 1)
           return $this->eq($values[0]);
 
        $qmarks = str_repeat(', ?', $count-1);
        return $this->raw("IN(?$qmarks)", $values);
    }
    /**
     * @alias any_of
     */
    public function in() {
        return call_user_func_array(array($this, 'any_of'), func_get_args());
    }
    /**
     * field LIKE '%?%'
     */
    public function like($value) {
        return $this->raw("LIKE CONCAT('%', ?, '%')", $value);
    }
    /**
     * field LIKE '?%'
     */
    public function start_with($value) {
        return $this->raw("LIKE CONCAT(?, '%')", $value);
    }
    /**
     * field LIKE '%?'
     */
    public function end_with($value) {
        return $this->raw("LIKE CONCAT('%', ?)", $value);
    }
}
?>
