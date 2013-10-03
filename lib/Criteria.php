<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

require __DIR__ .'/CriteriaIterator.php';
require __DIR__ .'/criteria/Condition.php';

class Criteria implements \IteratorAggregate {
    protected $model;
    protected $table;

    public static $logger = null;

    private $sth;

    public function __construct($model) {
        $this->model = $model;
        $this->table = $model::table();
        $this->fields_alias = $model::$alias_attribute;
        $this->where = array();
        $this->where_values = array();

        $this->limit = false;
        $this->offset = 0;
    }

    /**
     * Set a condition for our query.
     * @overload where($field, $value)
     *      Set an equalty condition, example:
     *      $q->where('name', 'Tito');
     *      Will fetch all record where name = 'Tito'
     * @return Criteria self
     *
     * @overload where($field)
     *      Let you do things like:
     *      $q->where('name')->neq('Tito')
     *      What it does is pretty obvious. See @Criteria\Conditions for 
     *      more. Notice that any subsequent methods will return the again 
     *      the Criteria object, so it's okay to do:
     *      $q->where('name')->start_with('T')->limit(10)
     * @return Criteria\Condition 
     */
    public function where($field, $value = false) {
        $field = $this->real_field_name($field);
        if($value === false)
            return new Criteria\Condition($this, $field);
        $this->_raw_condition("$field = ?", $field, $value);
        return $this;
    }
    public function _raw_condition($cond, $field, $value) {
        $this->where[] = $cond;
        if( !is_array($value) )
            $value = array($value);
        foreach($value as $v)
            $this->where_values[] = $this->process_value($field, $v);
        return $this;
    }
    /**
     * Limit the number of records.
     * Set 0 to nullify.
     * @params int $n How many record to retrive at max.
     */
    public function limit($n) {
        $this->limit = (int) $n;
        return $this;
    }
    /**
     * The record to start retrive from.
     * Set 0 to fetch from beginning.
     * @params int $n
     **/
    public function offset($n) {
        $this->offset = (int) $n;
        return $this;
    }

    /**
     * Return one model or false if nothing found
     * @return Model|bool $m|false
     */
    public function find_one() {
        return $this->limit(1)
                    ->fetch_model();
    }
    /**
     * Iterate through all the models and always returns an array
     * @return Array<Model> $list
     */
    public function find() {
        $list = array();
        foreach($this as $m)
            $list[] = $m;
        return $list;
    }
    /**
     * Alias of #find
     * @see #find
     */
    public function to_a() {
        return $this->find();
    }

    /**
     * @see #find_one
     */
    protected function fetch_model() {
        $sth = $this->query();
        $row = $sth->fetch();
        $this->log($row);
        if($row)
            return $this->model_factory($row);
        return false;
    }
    /**
     * Make an instance of our Model
     * @return Model $m
     */
    public function model_factory($attrs) {
        $this->log($attrs);
        $m = $this->model;
        return new $m($attrs, false, true, false);
    }

    /**
     *  This should not be used. It's public because needs to CriteriaIterator!
     *  @return PDOStatement $sth
     */
    public function query() {
        $values = $this->where_values;
        return $this->table->connection()->query($this->to_s(), $values);
    }

    /**
     * Let use aliased fields for ours query
     * @return string Correct field's name
     */
    protected function real_field_name($field) {
        if($field === 'id')
            return $this->table->pk[0];
        return array_key_exists($field, $this->fields_alias)
            ? $this->fields_alias[$field] : $field;
    }
    /**
     * Ensure to not mess with values
     */
    protected function process_value($name, $value) {
        return $this->table->process_value($name, $value);
    }

    /**
     * The where's values
     * @return array $where_values
     */
    protected function values() {
        return $this->where_values;
    }

    /**
     * Make the query statement
     * return string $query
     */
    public function to_s() {
        $sql = 'SELECT * FROM '. $this->table->to_s();
        $this
            ->parse_select()
            ->parse_where()
            ->parse_limit();
        return $this->sql;
    }
    public function __toString() {
        return $this->to_s();
    }

    protected function parse_select() {
        $this->sql = 'SELECT * FROM '. $this->table->to_s();
        return $this;
    }
    protected function parse_where() {
        if( !empty($this->where) )
            $this->sql .= ' WHERE (' . join(') AND (', $this->where) . ')';
        return $this;
    }
    protected function parse_limit() {
        if( !empty($this->limit) )
            $this->sql .= " LIMIT $this->offset, $this->limit";
        return $this;
    }

    public function getIterator() {
        return new Criteria\Iterator($this);
    }
    public function log($x) {
        if(self::$logger)
            self::$logger->log($x);
    }
}
?>
