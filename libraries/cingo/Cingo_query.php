<?php
/**
 * Rejiggers public methods from CodeIgniter's Query Builder for MongoDB
 *
 * PHP version 5
 *
 * @package CodeIgniter
 * @subpackage cingo
 */

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once('Cingo_base.php');
require_once('Cingo_compiled.php');
require_once('Cingo_result.php');

class Cingo_query extends Cingo_base
{

  public function __construct() {
    parent::__construct();
    $this->reset_query();
  }


  public function query($compiled) {
    $this->reset_query();
    $this->_collection = $compiled->query['collection'];
    $this->_projection = $compiled->query['projection'];
    $this->_filter = $compiled->query['filter'];
    $this->_sort = $compiled->query['sort'];
    $this->_limit = $compiled->query['limit'];
    $this->_skip = $compiled->query['skip'];
    $this->_distinct = $compiled->query['distinct'];
    return $this->_query();
  }


  public function get($collection=NULL, $limit=NULL, $offset=NULL) {
    return $this->_query($collection, NULL, $limit, $offset);
  }


  public function get_compiled_select($collection=NULL) {
    if (!is_null($collection)) {
      $this->from($collection);
    }
    if (!$this->_filter) {
      $this->_create_filter();
    }
    $query = array(
      'collection' => $this->_collection,
      'projection' => $this->_projection,
      'filter' => $this->_filter,
      'sort' => $this->_sort,
      'limit' => $this->_limit,
      'skip' => $this->_skip,
      'distinct' => $this->_distinct
    );
    return new Cingo_compiled($query);
  }


  public function get_where($collection=NULL, $where=NULL, $limit=NULL, $offset=NULL) {
    return $this->_query($collection, $where, $limit, $offset);
  }


  public function select($fields) {
    if (!is_array($fields)) {
      $fields = explode(',', $fields);
      $fields = array_map('trim', $fields);
    }
    $fields = array_filter($fields);
    if (!empty($fields)) {
      foreach ($fields as $field) {
        $this->_projection[$field] = 1;
      }
    }
    return $this;
  }


  public function select_max($field, $renamed=NULL) {
    if (!is_null($renamed)) {
      trigger_error('The $renamed parameter is currently ignored', E_USER_WARNING);
    }
    $this->select($field)->limit(1)->order_by($field, -1);
    return $this;
  }


  public function select_min($field, $renamed=NULL) {
    if (!is_null($renamed)) {
      trigger_error('The $renamed parameter is currently ignored', E_USER_WARNING);
    }
    $this->select($field)->limit(1)->order_by($field, 1);
    return $this;
  }


  public function select_avg($field, $renamed=NULL) {
    trigger_error('The select_avg() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function select_sum($field, $renamed=NULL) {
    trigger_error('The select_sum() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function from($collection) {
    if (empty($collection)) {
      trigger_error('Collection is blank', E_USER_ERROR);
    }
    $this->_collection = $collection;
    return $this;
  }


  public function join($colleciton, $join) {
    trigger_error('The join() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function mongo_where($var, $operator=NULL, $val=NULL) {
    $statements = $this->_prep_select($var, $val, $operator);
    return $this->_filter_statements('$and', $statements);
  }


  public function mongo_or_where($var, $operator=NULL, $val=NULL) {
    $statements = $this->_prep_select($var, $val, $operator);
    return $this->_filter_statements('$or', $statements);
  }


  public function mongo_like($var, $operator=NULL, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, $operator, $wildcard);
    return $this->_filter_statements('$and', $statements);
  }


  public function mongo_or_like($var, $operator=NULL, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, $operator, $wildcard);
    return $this->_filter_statements('$or', $statements);
  }


  public function where($var, $val=NULL, $protect=TRUE) {
    $statements = $this->_prep_select($var, $val);
    return $this->_filter_statements('$and', $statements);
  }


  public function not_where($var, $val=NULL, $protect=TRUE) {
    $statements = $this->_prep_select($var, $val, '$not');
    return $this->_filter_statements('$and', $statements);
  }


  public function or_where($var='', $val=NULL, $protect=TRUE) {
    $statements = $this->_prep_select($var, $val);
    return $this->_filter_statements('$or', $statements);
  }


  public function where_in($field, array $in) {
    $statements = $this->_prep_select($field, $in, '$in');
    return $this->_filter_statements('$and', $statements);
  }


  public function or_where_in($field, array $in) {
    $statements = $this->_prep_select($field, $in, '$in');
    return $this->_filter_statements('$or', $statements);
  }


  public function where_not_in($field, array $nin) {
    $statements = $this->_prep_select($field, $nin, '$nin');
    return $this->_filter_statements('$and', $statements);
  }


  public function or_where_not_in($field, array $nin) {
    $statements = $this->_prep_select($field, $nin, '$nin');
    return $this->_filter_statements('$or', $statements);
  }


  public function like($var, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, '', $wildcard);
    return $this->_filter_statements('$and', $statements);
  }


  public function or_like($var, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, '', $wildcard);
    return $this->_filter_statements('$or', $statements);
  }


  public function not_like($var, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, '$not', $wildcard);
    return $this->_filter_statements('$and', $statements);
  }


  public function or_not_like($var, $val=NULL, $wildcard='both') {
    $statements = $this->_prep_select($var, $val, '$not', $wildcard);
    return $this->_filter_statements('$or', $statements);
  }


  public function group_by($fields) {
    trigger_error('The group_by() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function distinct() {
    $this->_distinct = TRUE;
  }


  public function having($var, $val=NULL) {
    trigger_error('The having() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function not_having($var, $val=NULL) {
    trigger_error('The having() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function order_by($field, $direction='DESC') {
    switch ($direction) {
      case 'ASC':
        $direction = 1;
        break;
      case 'DESC':
        $direction = -1;
        break;
      case 'RANDOM':
        $direction = NULL;
        break;
      case 1:
        break;
      case -1:
        break;
      default:
        trigger_error('Sort must be one of 1, -1, ASC, DESC, or RANDOM', E_USER_ERROR);
    }
    if (!is_null($direction)) {
      $this->_sort[$field] = $direction;
    }
  }

  public function limit($limit, $offset=NULL) {
    if (!is_int($limit) || $limit < 1) {
      trigger_error('Limit must be an integer greater than 0', E_USER_ERROR);
    }
    $this->_limit = $limit;
    if (!is_null($offset)) {
      #if (!is_int($offset) || $offset < 1) {
      #  trigger_error('Offset must be an integer greater than 0', E_USER_ERROR);
      #}
      #$this->_skip = $offset;
    }
    return $this;
  }


  public function count_all_results($collection=NULL, $keep_select=FALSE) {
    // In the original CI function, select fields are cleared when this
    // func. is triggered
    if (!$keep_select) {
      $this->_projection = array();
    }
    $args = array(
      'count' => $this->_collection,
      'query' => $this->_filter,
    );
    if (!empty($this->_limit)) { $args['limit'] = $this->_limit; }
    if (!empty($this->_skip)) { $args['skip'] = $this->_skip; }
    $cmd = new MongoDB\Driver\Command($args);
    $cursor = $this->manager->executeCommand($this->database, $cmd);
    foreach ($cursor as $doc) {
      return $doc->n;
    }
  }


  public function count_all($collection) {
    $args = array('count' => $this->_collection);
    $cmd = new MongoDB\Driver\Command($args);
    $cursor = $this->manager->executeCommand($this->database, $cmd);
    foreach ($cursor as $doc) {
      return $doc->n;
    }
  }


  public function group_start() {
    $parent = $this->_groups[$this->_group_index]['parent'];
    trigger_error('The group_start() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function or_group_start() {
    trigger_error('The or_group_start() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function or_not_group_start() {
    trigger_error('The or_not_group_start() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function group_end() {
    trigger_error('The group_end() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function reset_query() {
    $this->_collection = NULL;
    $this->_projection = array();
    $this->_filter = array();
    $this->_sort = array();
    $this->_limit = NULL;
    $this->_skip = NULL;
    $this->_distinct = FALSE;

    $this->_first = TRUE;
    $this->_groups = array();
    $this->_create_group('$and');
    $this->_create_group('$or', 0);
  }


  public function start_cache() {
    trigger_error('The start_cache() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function stop_cache() {
    trigger_error('The stop_cache() method has not been implemented in Cingo', E_USER_ERROR);
  }


  public function flush_cache() {
    trigger_error('The flush_cache() method has not been implemented in Cingo', E_USER_ERROR);
  }

}
?>
