<?php
/**
 * Container allowing echoing and re-use of compiled MongoDB searches
 *
 * PHP version 5
 *
 * @package CodeIgniter
 * @subpackage cingo
 */

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cingo_compiled
{

  private $query;

  public function __construct($query) {
		$this->query = $query;
	}

  public function __get($name) {
    return $this->query;
  }

  function __toString() {
    return '<pre>' . print_r($this->query, TRUE) . '</pre>';
  }

}
?>
