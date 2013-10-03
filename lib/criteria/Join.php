<?php
namespace ActiveRecord\Criteria;

class JoinError extends Exception {
    public static function not_a_table($tbl) {
        $msg = "`$tbl` must be an instance of Table, a name of a Model or the name of model's table";
        return new self($msg);
    }
    
    public static function missed($miss) {
        $msg = 'You forgot some is_main_table joins: ' . join(', ', $miss);
        return new self($msg);
    }
    
}

class Join {
    protected $type;
    protected $criteria;
    protected $tblA, $tblB;
    protected $is_main_table;

    public function __construct($criteria, $tblA, $is_main_table = false) {
        $this->type = '';
        $this->criteria = $criteria;
        $this->is_main_table = $is_main_table;
        $this->fieldA = $this->fieldB = 'id';

        $this->tblA = $this->ensure_table($tblA);
    }

    protected function build() {
        if($this->is_main_table) {
            $reqd = false;
            $main = $this->tblB->to_s(false);
        }
        else {
            $main = $this->tblA->to_s(false);
            $reqd = $this->tblB->to_s(false);
        }

        $fieldA = $this->tblA->field_real_name($this->fieldA, true);
        $fieldB = $this->tblB->field_real_name($this->fieldB, true);
        $join = $this->type . "JOIN `$main` ON $fieldA = $fieldB";

        return $this->criteria->_join($main, $join, $reqd);
    }
    public function left() {
        $this->type = 'LEFT ';
        return $this;
    }
    public function right() {
        $this->type = 'RIGHT ';
        return $this;
    }
    public function with_relation($rel) {
        if( is_string($rel) )
            $rel = $this->tblA->get_relationship($rel, true);

        $this->tblB = $rel->get_table();
        $this->fieldA = $rel->my_field();
        $this->fieldB = $rel->foreign_field();

        return $this->build();
    }
    public function with_model($m) {
        $this->tblB = Table::load($m);
        return $this;
    }
    public function with($tlbB) {
        $this->tblB = $this->ensure_table($tblB);
        return $this;
    }
    public function on($fieldA, $fieldB) {
        $this->fieldA = $fieldA;
        $this->fieldB = $fieldB;
        return $this->build();
    }
 
    protected function ensure_table($tbl) {
        if($tbl instanceof Table)
            return $tbl;

        if( !class_exists($tbl) ) {
            $tbl = classify($tbl, true);
            if( !class_exists($tbl) )
                throw JoinError::not_a_table($tbl);
        }
        return Table::load($tbl);
    }
}
?>
