<?php
/**
 * Manage logics behind conditions.
 * You can easily extends this class through `raw` and then injecting your 
 * own class into ActiveRecord\Criteria.
 *
 * Let's say we write a stored mysql function and want to use it in our 
 * queries, with this kind of interface:
 *
 * <code lang="php">
 * Model::where('field')->match_my_awesome_func_with(10);
 * </code>
 *
 * We could use the `Condition#raw` method, but we need this snippet of code 
 * in a lot of queries, then the best solution is extends this class and 
 * then inject it!
 *
 * <code lang="php">
 * class MyCondition extends ActiveRecord\Criteria\Condition {
 *     public function match_my_awesome_func_with($val) {
 *         return $this->raw('= MY_AWESOME_FUNC(?)', $val);
 *     }
 * }
 *
 * ActiveRecord\Criteria::inject_condition_class('MyCondition');
 * </code>
 *
 * Very easy and very flexible.
 */
namespace ActiveRecord\Criteria;

class Condition {
    protected
        $parent, $field, $op, $value;
    public function __construct($criteria, $field, $table, $logic) {
        $this->parent = $criteria;
        $this->table = $table;
        $this->logic = $logic;
        $this->field = $this->table->field_real_name($field);
        return $this;
    }
    
    /**
     * Use this method for condition complex as you wish
     * @params string $op     Operator. Example: '= ?'
     * @params mixed  $value  Some value, can be anything convertible to 
     *                        string
     */
    public function raw($op, $value) {
        $this->op = $op;
        $this->value = &$value;
        if( !is_array($value) )
            $value = array($value);
        foreach($value as &$v)
            $v = $this->table->process_value($this->field, $v);

        return $this->parent->_add_condition($this);
    }
    public function logic() {
        return $this->logic;
    }
    public function value() {
        return $this->value;
    }
    public function to_s($scoped = false, $first = true) {
        $logic = ($first) ? '' : " $this->logic ";
        $field = $this->table->quote_name($this->field, $scoped);
        return "$logic$field $this->op";
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
     * @results field IN(?, ?, ...)
     *
     * @overload in($mixed)
     * @params Mixed $mixed Array with one element or anything else
     * @see #eq
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
    public function includes($value) {
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
    /**
     * It's like `eq` but ignore case
     *
     * field LIKE '?'
     */
    public function like($value) {
        return $this->raw("LIKE ?", $value);
    }
}
?>
