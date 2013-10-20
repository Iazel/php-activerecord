<?php
namespace ActiveRecord\Criteria;

class ConditionGroup {
    protected $parent, $table, $childs, $values;
    public static $condition_class;

    public function __construct($parent, $table) {
        $this->parent = $parent;
        $this->table = $table;
        $this->childs = array();

        $klass = get_class($parent);
        static::$condition_class = $klass::$condition_class;
    }

    public static function inject_condition_class($klass) {
        static::$condition_class = $klass;
    }
    

    // Public interface
    public function ors($field, $value = false) {
        return $this->new_cond($field, $value, 'OR');
    }
    public function ands($field, $value = false) {
        return $this->new_cond($field, $value, 'AND');
    }
    public function scope($t = false) {
        $this->table = $this->parent->scope($t)->get_scope();
    }
    public function condition_group() {
        return new self($this, $this->table);
    }
    public function end() {
        return $this->parent->_add_condition($this);
    }
    // End public interface

    protected function new_cond($field, $val, $logic) {
        $cond = new static::$condition_class(
            $this, $field, $this->table, $logic
        );
        return ($val === false) ? $cond : $cond->eq($val);
    }
    public function _add_condition($cond) {
        $this->childs[] = $cond;
        return $this;
    }
    public function value() {
        if( is_null($this->values) ) {
            $this->values = array_map(
                function($c){ return $c->value(); }, $this->childs);
        }
        return $this->values;
    }
    public function to_s($scoped = false, $first = true) {
        $i = 0;
        $sql = ' (';
        $c = $this->childs;
        $len = count($c);
        $vs = array();
        if($first === false) {
            ++$i;
            $sql = ' '. $c[0]->logic() . $sql . $c[0]->to_s($scoped, true);
            $vs[] = $c[0]->value();
        }
        for(; $i < $len; ++$i) {
            $vs[] = $c[$i]->value();
            $sql .= $c[$i]->to_s($scoped, false);
        }

        $this->values = $vs;
        return $sql . ')';
    }
}
?>
