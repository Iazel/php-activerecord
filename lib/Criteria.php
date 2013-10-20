<?php
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;

require __DIR__ .'/criteria/Error.php';
require __DIR__ .'/criteria/Iterator.php';
require __DIR__ .'/criteria/Join.php';
require __DIR__ .'/criteria/Condition.php';
require __DIR__ .'/criteria/ConditionGroup.php';

class Criteria implements \IteratorAggregate {
    protected $model;
    protected $table, $scope;

    protected $joins;
    protected $where, $where_values;
    protected $order;
    protected $limit, $offset;

    public static $logger = null;
    public static $condition_class = 'ActiveRecord\Criteria\Condition';

    private $sth;

    const ORDER_ASC  = 1;
    const ORDER_DESC = 2;

    public function __construct($model) {
        $this->model = $model;
        $this->table = $model::table();
        $this->where = array();
        $this->where_values = array();

        $this->offset = 0;
        $this->limit = false;
        $this->scope = false;
    }

    /**
     * Total number of matching records.
     * Ignore Order, Limits, Groups and Havings.
     *
     * @return int Number of matching records
     */
    public function count() {
        $this->sql = 'SELECT COUNT(*) FROM ' . $this->table->to_s();
        $sql = $this->parse_joins()
                    ->parse_where()
                    ->to_s();
        
        $values = $this->where_values;
        $sth = $this->table->connection()->query($sql, $values);
        $cnt = $sth->fetch(PDO::FETCH_NUM);
        return (int) $cnt[0];
    }

    /**
     * Join with other table. You don't need to join tables in the correct 
     * order.
     *
     * @see Criteria\Join
     */
    public function join($table = false) {
        if($table === false)
            return new Criteria\Join($this, $this->table);

        if( !$this->table->has_relationship($table) )
            return new Criteria\Join($this, $this->table, $table);
        
        $join = new Criteria\Join($this, $this->table);
        return $join->relation($table);
    }
    public function _join($table, $str, $required = false) {
        $this->scope();
        $t = $table->to_s(false);
        $this->joins[$t] = array('t' => $table, 's' => $str, 'r' => $required);
        return $this;
    }

    /**
     * Scope fields for a joined table. Automatically apply after the first 
     * join.
     * @overload scope()
     *      Scope to main table (default)
     * @overload scope($joined)
     *      Scope to joined table, but you must use Model's name
     * @example
     *      Author::all()->where('name', 'John')
     *          ->join('books')
     *          ->scope('Book')->condition_group()
     *            ->ands('name')->start_with('T')
     *            ->ors('length')->gte(100);
     *          ->end()->scope();
     *  Will produce something like:
     *      SELECT `authors`.* FROM `authors`
     *      JOIN `books` ON `authors`.`id` = `books`.`author_id`
     *      WHERE `authors`.`name` = 'John'
     *      AND (`books`.`name` LIKE 'T%' OR `books`.`length` >= 100)
     *
     *  You should always modify and reset scope in the same piece of code.
     */
    public function scope($t = false) {
        if($t === false || $t === $this->table)
            $this->scope = $this->table;
        elseif( array_key_exists($t, $this->joins) )
            $this->scope = $this->joins[$t]['t'];
        else
            throw Criteria\Error::should_join($t);

        return $this;
    }
    public function get_scope() {
        return $this->scope;
    }

    public function condition_group() {
        $t = $this->scope ?: $this->table;
        return new Criteria\ConditionGroup($this, $t);
    }

    /**
     * Set a condition for our query.
     * @overload where($field, $value)
     *      Set an equalty condition, example:
     *        $q->where('name', 'Tito')
     *      Will fetch all record where name = 'Tito'
     * @return $this
     *
     * @overload where($field)
     *      Let you do things like:
     *        $q->where('name')->neq('Tito')
     *      What it does is pretty obvious.
     *      Notice that any subsequent methods will return the original 
     *      Criteria object, so it's okay to do:
     *        $q->where('name')->start_with('T')->limit(10)
     * @see Criteria\Condition
     * @return Criteria\Condition 
     */
    public function where($field, $value = false, $logic = 'AND') {
        $cond = $this->condition_factory($field, $logic);
        return ($value === false) ? $cond : $cond->eq($value);
    }
    public function ands($field, $value = false) {
        return $this->where($field, $value, 'AND');
    }
    public function ors($field, $value = false) {
        return $this->where($field, $value, 'OR');
    }
    public function _add_condition($cond) {
        $this->where[] = $cond;
        return $this;
    }
    protected function condition_factory($field, $logic) {
        $scope = $this->scope ?: $this->table;
        return new static::$condition_class($this, $field, $scope, $logic);
    }

    /**
     * Limit the max number of records.
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
     * Return first model or false if nothing found
     * @return Model|bool $m|false
     */
    public function first() {
        return $this->limit(1)
                    ->fetch_model();
    }
    /**
     * Return last model or false if nothing found
     * @return Model|bool $m|false
     */
    public function last() {
        return $this->inverse_order()->first();
    }

    /**
     * Set ascending (ASC) order for the proper field.
     */
    public function order_by($field) {
        $field = $this->field_real_name($field);
        $this->order[$field] = self::ORDER_ASC;
        return $this;
    }
    /**
     * Set descending (DESC) order for the proper field.
     */
    public function order_by_desc($field) {
        $field = $this->field_real_name($field);
        $this->order[$field] = self::ORDER_DESC;
        return $this;
    }
    /**
     * Reverse the setted order
     */
    public function reverse_order() {
        foreach($this->order as &$dir)
            $dir = !$dir;
        return $this;
    }

    /**
     * @see #find
     */
    public function to_a() {
        return $this->find();
    }
    public function all() {
        return $this->find();
    }
    /**
     * Iterate through all models and always returns an array.
     * You should not use this method, expecially if the expected results 
     * will be a huge collection, because this will put ALL your models in
     * memory.
     * Instead, you should iterate using a `foreach`, so only the current 
     * model will use memory.
     *
     * @return Array<Model>
     */
    public function find() {
        $list = array();
        foreach($this as $m)
            $list[] = $m;
        return $list;
    }

    /**
     * @see #first
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
     * @return Model
     */
    public function model_factory($attrs) {
        $this->log($attrs);
        $m = $this->model;
        return new $m($attrs, false, true, false);
    }

    /**
     *  This should not be used. It's public because needs to CriteriaIterator!
     *  @return PDOStatement
     */
    public function query() {
        $sql = $this->to_s();
        $val = $this->values();
        return $this->table->connection()->query($sql, $val);
    }

    protected function quote_name($field) {
        return $this->scope === false
             ? $this->table->quote_name($field)
             : $this->scope->quote_name($field, true);
    }
    /**
     * Let use aliased fields for ours query
     * @return string Correct field's name
     */
    protected function field_real_name($field) {
        return $this->table->field_real_name($field);
    }

    /**
     * Make the query statement
     * return string $query
     */
    public function to_s() {
        $this
            ->parse_select()
            ->parse_joins()
            ->parse_where()
            ->parse_order()
            ->parse_limit();
        return $this->sql;
    }
    public function __toString() {
        return $this->to_s();
    }
    /**
     * Only for test purpose, you shouldn't need this method.
     */
    public function values() {
        return $this->where_values;
    }

    protected function parse_select() {
        $t = $this->table->to_s();
        $this->sql = "SELECT $t.* FROM $t";
        return $this;
    }
    protected function parse_joins() {
        if( empty($this->joins) )
            return $this;

        $joins = $this->joins;
        $joined = array($this->table->to_s(false));
        do {
            $hit = false;
            $missed = array();
            foreach($joins as $k => $j) {
                if( $j['r'] !== false && !in_array($j['r'], $joined) ) {
                    $missed[] = $j;
                    continue;
                }
                $this->sql .= "\n$j[s]";
                $hit = true;
            }
            if($hit === false)
                throw Criteria\JoinError::missed($missed);

            $joins = $missed;
        } while( !empty($joins) );
    }
    protected function parse_where() {
        if( empty($this->where) )
            return $this;
        $first = true;
        $this->sql .= ' WHERE ';
        $this->where_values = array();
        $scoped = $this->scope !== false;
        foreach($this->where as $cond) {
            $this->sql .= $cond->to_s($scoped, $first);
            $this->where_values = array_merge(
                $this->where_values, $cond->value()
            );
            $first = false;
        }
        return $this;
    }
    protected function parse_order() {
        if( empty($this->order) )
            return $this;
        $o = '';
        foreach($this->order as $field => $dir)
            $o .= ", `$field` " . ($dir === self::ORDER_ASC ? 'ASC' : 'DESC');
        $this->sql .= ' ORDER BY' . substr($o, 1);
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
