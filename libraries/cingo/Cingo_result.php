<?php
/**
 * Reproduces public methods of CodeIgniter Result object for MongoDB cursor
 *
 * PHP version 5
 *
 * @package CodeIgniter
 * @subpackage cingo
 */

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Cingo_result
{

  protected $cursor;
  protected $_num_rows;


  public function __construct($cursor, $num_rows=0) {
		$this->cursor	= $cursor;
    $this->_num_rows = $num_rows;
	}


  public function result() {
    $this->cursor->setTypeMap(array('root' => 'object', 'document' => 'array'));
    return $this->cursor;
  }


  public function result_array() {
    $this->cursor->setTypeMap(array('root' => 'array', 'document' => 'array'));
    return $this->cursor;
  }


  public function row() {
    foreach ($this->cursor as $row) {
      return (object) $row;
    }
    trigger_error('Cannot retrieve row from empty cursor', E_USER_WARNING);
  }


  public function row_array() {
    foreach ($this->cursor as $row) {
      return (array) $row;
    }
    trigger_error('Cannot retrieve row from empty cursor', E_USER_WARNING);
  }


  public function unbuffered_row() {
    trigger_error('The unbuffered_row() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function num_rows() {
    return $this->_num_rows;
  }


  public function pprint($obj) {
    echo '<pre>'; print_r($obj); echo '</pre>';
  }

  private function to_object($arr) {
    return (object) $arr;
  }

}
?>
