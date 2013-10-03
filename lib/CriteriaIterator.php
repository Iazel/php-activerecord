<?php
namespace ActiveRecord\Criteria;

class Iterator implements \Iterator {
    protected $sth;
    protected $row_num, $row;
    protected $criteria;

    public function __construct($criteria) {
        $this->criteria = $criteria;
        $this->current = false;
        $this->row_num = 1;
    }
    
    public function rewind() {
        $this->sth = $this->criteria->query();
    }
    public function valid() {
        $this->row = $this->sth->fetch();
        return $this->row !== false;
    }
    public function next() {
        $this->row_num += 1;
        $this->current = $this->criteria->model_factory($this->row);
    }
    public function key() {
        return $this->row_num;
    }
    public function current() {
        return $this->current;
    }
}
?>
