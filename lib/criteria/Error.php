<?php
namespace ActiveRecord\Criteria;

class Error extends \Exception {
    public static function should_join($t) {
        return new self("You can't scope `$t` table because it's not joined");
    }
}
?>
