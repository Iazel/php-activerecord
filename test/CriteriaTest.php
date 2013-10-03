<?php
include 'helpers/config.php';

class CriteriaTest extends DatabaseTest {
    protected static $logger;
    public static function setUpBeforeClass() {
        self::$logger = new Log_file(dirname(__FILE__) . '/log/fetch.log','ident',array('mode' => 0664, 'timeFormat' =>  '%Y-%m-%d %H:%M:%S'));
    }
    public function set_up($connection = null) {
        //~ ActiveRecord\Criteria::$logger = self::$logger;
        parent::set_up($connection);
    }
    
    public function test_find_one() {
        // Find one record
        $a = Author::where('name', 'Tito')->find_one();
        $this->assert_instance_of('Author', $a);
        $this->assert_equals(1, $a->id);
    }
    public function test_criteria_is_iteratable() {
        $authors = Author::all();
        $last_id = -1;
        foreach($authors as $a) {
            $this->assert_instance_of('Author', $a);
            $this->assert_not_equals($last_id, $a->id);
            $last_id = $a->id;
        }
    }
    public function test_all_returns_criteria() {
        $a = Author::all();
        $this->assert_instance_of('ActiveRecord\Criteria', $a);
    }
    public function test_use_find_for_list_all() {
        $array = Author::all()->find();
        $this->assert_internal_type('array', $array);
        $this->assert_not_empty($array);
        $this->assert_equals($array, Author::all()->to_a());
    }
    public function test_where_with_one_argument_returns_a_condition() {
        $authors = Author::where('name');
        $this->assert_instance_of('ActiveRecord\Criteria\Condition', $authors);
    }
    /**
     * @group wip
     */
    public function test_all_condtions() {
        $authors = Author::where('name')->eq('Tito')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->neq('Tito')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->start_with('T')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->end_with('o')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->like('uncle')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->any_of('Tito', 'Uncle Bob')->find();
        $this->assert_count(2, $authors);
        $authors = Author::where('id')->between(1, 3)->find();
        $this->assert_count(3, $authors);
        $authors = Author::where('id')->lt(2)->find();
        $this->assert_count(1, $authors);
        $authors = Author::where('id')->lte(2)->find();
        $this->assert_count(2, $authors);
        $authors = Author::where('id')->gt(2)->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('id')->gte(2)->find();
        $this->assert_not_empty($authors);
    }
}
?>
