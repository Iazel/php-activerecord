<?php
namespace ActiveRecord\Criteria;

class JoinError extends Error {
    public static function not_a_table($tbl) {
        $msg = "`$tbl` must be an instance of Table, a name of a Model or the name of model's table";
        return new self($msg);
    }
    public static function missed($miss) {
        $msg = 'You forgot some is_main_table joins: ' . join(', ', $miss);
        return new self($msg);
    }
    public static function need_target_table() {
        return new self('You should set a Target Table');
    }
    
}

/**
 * Let easily perform JOIN. The criteria will also ensure the correct order 
 * of joins, so any join will be after the table it depends on, even if you 
 * joined it later.
 * You can't use this with a 
 * @examples
 *  Most common case: join a model with a relation; it's a piece of cake
 *    Author::all()->join('books');
 *  This version use Criteria\Join#relation
 *
 *  Less common but can happen: join with a third different table
 *    Author::all()->join('books')->join('chapters')->with('books');
 *  In this case Book and Chapter have a relation.
 *  You can also do this:
 *    Author::all()->join('chapters')->with('books')
 *                 ->join('books');
 *  It's equal to the former one, in the real join `books` will joined 
 *  before `chapters` (to be honest, this version is slightly slower)
 *
 *  Rare case: join table which doesn't have a direct relation
 *    Author::all()->join('cars')->on('name');
 *  This will join Author and Car with the same name.
 *  Arbitrary tables name aren't supported and should always refers to a
 *  model.
 *  In this cases it's better and more efficient use Model's name:
 *    Author::all()->join('Car')->on('name');
 *
 *  Join on two different fields:
 *    Author::all()->join('Car')->on('date_of_birth', 'immatr');
 *
 *  You can also use the `with` method:
 *    Author::all()->join('books')
 *                 ->join('Car')->with('Book')->on('published_at', 'immatr');
 *
 *  Rule of Thumb: first parameter of `on` refers to table main table (or
 *  the set through `with`), while the other one will refers to joining
 *  table (`cars`).
 *
 *  You can also join right or left, but if it's a join with a relation, you 
 *  have some extra work to do:
 *    Author::all()->join()->left()->relation('books');
 *  For RIGHT JOIN, obviously substitute `left()` with `right()`
 */ 
class Join {
    protected $type;
    protected $criteria;
    protected $tblA, $tblT;
    protected $is_main_table;

    public function __construct($criteria, $tblA, $tblT = false) {
        $this->type = '';
        $this->criteria = $criteria;
        $this->is_main_table = ($tblT === false);

        $this->tblA = $this->ensure_table($tblA);
        $this->tblT = $tblT;

        $this->fieldA = $this->fieldT = 'id';
    }

    public function build() {
        if($this->tblT === false)
            throw JoinError::need_target_table();

        $main = $this->tblT;
        $reqd = $this->is_main_table ? false : $this->tblA->to_s(false);

        $table = $main->to_s();
        $fieldA = $this->tblA->field_real_name($this->fieldA, true);
        $fieldT = $this->tblT->field_real_name($this->fieldT, true);
        $join = $this->type . "JOIN `$table` ON $fieldA = $fieldT";

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

    /**
     * Set target table.
     */
    public function table($t) {
        $this->tblT = $this->ensure_table($t);
        return $this;
    }
    /**
     * Expect a relation with $tblA
     */
    public function relation($rel) {
        if( is_string($rel) )
            $rel = $this->tblA->get_relationship($rel, true);

        $this->set_from_rel($rel, 'A');

        return $this->build();
    }
    /**
     * Set table used for join.
     * @overload with($relA)
     * If $relA is a relation of target table, this will be enough to 
     * complete join.
     * @return Criteria
     *
     * @overload with($tblA)
     * Otherwise, you need to specify fields also
     * @return self
     */
    public function with($tblA) {
        if($this->tblT === false)
            throw JoinError::need_target_table();

        $rel = $this->tblT->get_relationship($tblA);
        if($rel) {
            $this->set_from_rel($rel, 'T');
            return $this->build();
        }

        $this->tblA = $this->ensure_table($tblA);
        return $this;
    }
    /**
     * @nodoc
     * D.R.Y. got too far?
     */
    protected function set_from_rel($rel, $im) {
        $he = ($im === 'A') ? 'T' : 'A';
        $this->{"tbl$he"} = $rel->get_table();
        $this->{"field$im"} = $rel->my_field();
        $this->{"field$he"} = $rel->foreign_field();
    }
    /**
     * Aliases can be used.
     *
     * @overload on($field)
     * @params $field Same field name for both tables. If it's an alias, will be 
     *                converted differently.
     *
     * @overload on($fieldA, $fieldT)
     * @params string $fieldA Field of table to which join
     * @params string $fieldT Field of table to join (target)
     */
    public function on($fieldA, $fieldT = false) {
        if($fieldT === false)
            $fieldT = $fiedlA;

        $this->fieldA = $fieldA;
        $this->fieldT = $fieldT;
        return $this->build();
    }
 
    protected function ensure_table($tbl) {
        if($tbl instanceof Table)
            return $tbl;

        if( !class_exists($tbl) ) {
            $t = $tbl;
            $tbl = classify($tbl, true);
            if( !class_exists($tbl) )
                throw JoinError::not_a_table($t);
        }
        return Table::load($tbl);
    }
}
?>
