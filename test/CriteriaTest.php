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
    
    public function test_all_first_and_last() {
        // Find first record matching criteria
        $a = Author::where('name', 'Tito')->first();
        $this->assert_instance_of('Author', $a);
        $this->assert_equals(1, $a->id);

        // Find the last record matching criteria
        $a = Author::where('name', 'Tito')->last();
        $this->assert_instance_of('Author', $a);
        $this->assert_equals(5, $a->id);

        // Find all records matching criteria and return an array.
        $a = Author::where('name', 'Tito')->all();
        $this->assert_count(2, $a);

        // Find all records, but you should iterate over each one. This
        // could save a lot of memory if you doesn't really need all records
        // at once (most frequent case).
        $authors = Author::all();
        $this->assert_instance_of('ActiveRecord\Criteria', $authors);
        $last_id = -1;
        foreach($authors as $a) {
            $this->assert_instance_of('Author', $a);
            $this->assert_not_equals($last_id, $a->id);
            $last_id = $a->id;
        }

        // Using #all is really convenient, because it actually returns a 
        // Criteria object, so you can also add other constraints later:
        $authors = Author::all();
        $this->assert_instance_of('ActiveRecord\Criteria', $authors);
        // ... all the code you want other code ...
        $authors->where('name')->eq('Tito');
        // then you can also decide to fetch only the first / last one
        $a = $authors->first();
        $this->assert_instance_of('Author', $a);

        // And you can still iterate over all the matching records, filter 
        // more and all the other things.

        // If you need to count all results, use #count
        $count = $authors->count();
        $this->assert_equals(2, $count);
    }
    /**
     * @group wip
     */
    public function test_condtions() {
        $authors = Author::where('name');
        $this->assert_instance_of('ActiveRecord\Criteria\Condition', $authors);

        // List of all available conditions
        $authors = Author::where('name')->eq('Tito')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->neq('Tito')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->start_with('T')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->end_with('o')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->includes('uncle')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->like('uncle bob')->find();
        $this->assert_not_empty($authors);
        $authors = Author::where('name')->any_of('Tito', 'Uncle Bob')->find();
        $this->assert_count(3, $authors);
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

        // For special cases, use `Condition#raw` method
        $sql = Author::where('name')->raw('= SPECIAL_FUNC(?)', 'val')->to_s();
        $this->assert_contains('`name` = SPECIAL_FUNC(?)', $sql);

        $c = Venue::where('name')
            ->start_with('The ')
            ->ands('state', 'VA');
        $expect = "WHERE `name` LIKE CONCAT(?, '%') AND `state` = ?";
        $this->assert_contains($expect, $c->to_s());
        $this->assert_equals(array('The ', 'VA'), $c->values());

        $sql = Venue::where('name')->start_with('The ')
            ->ands('state')->eq('VA')
            ->to_s();
        $this->assert_contains($expect, $sql);

        $sql = Venue::where('name')->start_with('The ')
            ->ors('state', 'DC')
            ->to_s();
        $expect = str_replace('AND', 'OR', $expect);
        $this->assert_contains($expect, $sql);

        $sql = Venue::where('phone', '2222')
            ->condition_group()
                ->ands('state', 'NY')
                ->ors('name')->eq('The ')
            ->end()
            ->to_s();
        $expect = "WHERE `phone` = ? AND (`state` = ? OR `name` = ?)";
        $this->assert_contains($expect, $sql);

        $sql = Venue::where('phone', '2222')
            ->condition_group()
                ->ors('state', 'NY')
                ->ands('name')->eq('The ')
            ->end()
            ->ands('city', 'New York')
            ->to_s();
        $expect = "WHERE `phone` = ? OR (`state` = ? AND `name` = ?)";
        $this->assert_contains($expect, $sql);
    }

    public function test_joins() {
        /*
        $s = Service::all()
            ->join('categorie_servizio', 'ct')
            ->join('lista_province', 'prv')
                ->through('comuni')
            ->join('caratteristiche_struttura', 'crs')
            ->join('tariffe_servizio', 'ts')
            ->join('ubicazione', 'ub')
            Service::all()
            ->with('category')->eq(Service::ALLEVAMENTO)
         */
    }
}
?>
