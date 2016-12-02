<?php
/**
 * Backend functions to format CodeIgniter Query Builder queries for MongoDB
 *
 * PHP version 5
 *
 * @package CodeIgniter
 * @subpackage cingo
 */

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once('Cingo_connect.php');
require_once('Cingo_fake_cursor.php');
require_once('Cingo_result.php');

class Cingo_base
{

  protected $connection;
  protected $manager;
  protected $database;

  protected $_collection;
  protected $_projection;
  protected $_filter;
  protected $_sort;
  protected $_limit;
  protected $_skip;
  protected $_distinct;

  protected $_first;
  protected $_groups;
  protected $_group_index;

  protected $operators = array(
    '$all',
    '$and',
    '$eq',
    '$exists',
    '$in',
    '$gt',
    '$gte',
    '$lt',
    '$lte',
    '$ne',
    '$nin',
    '$nor',
    '$not',
    '$or',
    '$size',
    '$type'
  );

  protected $operator_map = array(
    ' is null' => array('operator' => '$exists', 'value' => FALSE),
    ' is not null' => array('operator' => '$exists', 'value' => TRUE),
    ' !=' => array('operator' => '$ne', 'value' => NULL),
    ' >' => array('operator' => '$gt', 'value' => NULL),
    ' >=' => array('operator' => '$gte', 'value' => NULL),
    ' <' => array('operator' => '$lt', 'value' => NULL),
    ' <=' => array('operator' => '$lte', 'value' => NULL)
  );

  public function __construct() {
    $this->connection = new Cingo_connect();
    $this->manager = $this->connection->manager;
    $this->database = $this->connection->database;
    $this->reset_query();
  }


  public function pprint($obj, $return=FALSE) {
    if ($return) {
      return '<pre>' . print_r($obj, $return) . '</pre>';
    }
    else {
      echo '<pre>'; print_r($obj); echo '</pre>';
    }
  }


  protected function _create_filter() {
    if (count($this->_group_index) == 1 && !$this->_groups[$this->_group_index]['group']) {
      $this->_filter = array();
      return;
    }
    // Find all groups that have no children
    $children = array();
    foreach ($this->_groups as $group) {
      if (!array_key_exists('children', $group)) {
        $children[] = $group;
      }
    }
    // Populate tree from the bottom up
    $filter = array();
    while ($children) {
      $_children = array();
      foreach ($children as $child) {
        $parent = &$this->_groups[$child['parent']];
        $parent['subgroup'][] = $this->_group_to_filter($child);
      }
      $children = $_children;
    }
    // Return the filter
    $this->_filter = $this->_group_to_filter($parent);
  }


  protected function _create_group($conj, $parent=NULL) {
    $group = array(
      'conj' => $conj,
      'parent' => $parent,
      'group' => array(),
    );
    $this->_groups[] = $group;
    $this->_group_index = count($this->_groups) - 1;
    if (!is_null($parent)) {
      $this->_groups[$parent]['children'][] = $this->_group_index;
    }
    return $this->_group_index;
  }


  protected function _create_pattern($val, $wildcard, $flags) {
    return new MongoDB\BSON\Regex($wildcard['before'] . $val . $wildcard['after'], $flags);
  }


  protected function _create_query($collection, $where, $limit, $offset) {
    if (!is_null($collection)) { $this->from($collection); }
    if (!is_null($where)) { $this->where($where); }
    if (!is_null($limit) || !is_null($offset)) { $this->limit($limit, $offset); }
    // Verify that collection is set. This is the only required parameter.
    if (empty($this->_collection)) {
      trigger_error('No collection specified', E_USER_ERROR);
    }
    // Create filter from groups of query statements
    if (!$this->_filter) {
      $this->_create_filter();
    }
    // Set options
    $options = array();
    if (!empty($this->_limit)) { $options['limit'] = $this->_limit; }
    if (!empty($this->_projection)) { $options['projection'] = $this->_projection; }
    if (!empty($this->_skip)) { $options['skip'] = $this->_skip; }
    if (!empty($this->_sort)) { $options['sort'] = $this->_sort; }
    // Return the formatted query
    return new MongoDB\Driver\Query($this->_filter, $options);
  }


  protected function _filter_statements($operator='$and', $statements=array()) {
    // The first statement is always treated as an $or
    if ($this->_first) {
      $operator = '$or';
    }
    elseif ($operator == '$and') {
      $this->_create_group('$or', $this->_groups[$this->_group_index]['parent']);
    }
    foreach ($statements as $statement) {
      $this->_groups[$this->_group_index]['group'][] = $statement;
    }
    $this->_first = FALSE;
    return $this;
  }


  protected function _find_operators($field, $value) {
    $lc_field = strtolower($field);
    foreach ($this->operator_map as $op => $retval) {
      if (substr($lc_field, -strlen($op)) == $op) {
        return array('operator' => $retval['operator'],
                     'field' => substr($field, 0, strlen($field) - strlen($op)),
                     'value' => is_null($retval['value']) ? $value : $retval['value']);
      }
    }
    return array('operator' => NULL,
                 'field' => $field,
                 'value' => $value);
  }


  protected function _group_to_filter($group) {
    // Check for substatements created from children of this group
    if (array_key_exists('subgroup', $group)) {
      if (!$group['group']) {
        $group['group'] = $group['subgroup'];
      }
      else {
        $group['group'][] = $group['subgroup'];
      }
    }
    // Strip the outermost conjunction if only one statement
    if (count($group['group']) == 1) {
      return $group['group'][0];
    }
    else {
      return array($group['conj'] => $group['group']);
    }
  }


  protected function _prep_select($var, $val, $operator='', $wildcard='none') {
    // Reset filter if changes are made
    $this->_filter = NULL;
    // Validate parameters to confirm that query is legal
    $operator = $this->_validate_operator($operator);
    $wildcard = $this->_validate_wildcard($wildcard);
    // Parse $var and $val to determine how keys/values have been passed
    if (is_array($var) && !is_null($val)) {
      trigger_error('$val should be null if $var is an array', E_USER_WARNING);
    }
    elseif (!is_array($var)) {
      if (strpos($var, ' ') !== FALSE) {
        // If $var is not an array, it is treated as a field name
        $retval = $this->_find_operators($var, $val);
        if (!is_null($retval['operator'])) {
          $operator = $retval['operator'];
        }
        $var = $retval['field'];
        $val = $retval['value'];
      }
      $var = array($var => $val);
      $val = NULL;
    }
    $query = array();
    foreach ($var as $key => $val) {
      // Create regex patterns if $wildcard is specified
      if ($wildcard) {
        if (is_array($val)) {
          foreach ($val as $i => $v) {
            $val[$i] = $this->_create_pattern($val, $wildcard, 'i');
          }
        }
        else {
          $val = $this->_create_pattern($val, $wildcard, 'i');
        }
      }
      // Add operators
      if (!empty($operator)) {
        $query[] = array($key => array($operator => $val));
      }
      else {
        $query[] = array($key => $val);
      }
    }
    return $query;
  }


  public function _query($collection=NULL, $where=NULL, $limit=NULL, $offset=NULL) {
    if ($this->_distinct) {
      // Break the result of a distinct query into documents (instead of returning
      // a single document with multiple values)
      if (count($this->_projection) != 1) {
        trigger_error('Multiple fields specified for a MongoDB distinct query', E_USER_WARNING);
      }
      if ($this->_limit || $this->_skip) {
        trigger_error('Limit or skip parameter set for a MongoDB distinct query', E_USER_WARNING);
      }
      $field = array_keys($this->_projection)[0];
      $cmd = new MongoDB\Driver\Command([
        'distinct' => $this->_collection,
        'key' => $field,
        'query' => $this->_filter
      ]);
      $cursor = $this->manager->executeCommand($this->database, $cmd);
      // Recast result as an array
      $results = array();
      foreach ($cursor as $doc) {
        foreach ($doc->values as $val) {
          $obj = new stdClass();
          $obj->$field = $val;
          $results[] = $obj;
        }
      }
      // Sort results by value, if projected and sort fields are the same
      if ($this->_sort && $field == array_keys($this->_sort)[0]) {
        sort($results);
      }
      $cursor = new Cingo_fake_cursor($results);
      $num_rows = count($results);
    }
    else {
      $query = $this->_create_query($collection, $where, $limit, $offset);
      $datasource = $this->database . '.' . $this->_collection;
      $cursor = $this->manager->executeQuery($datasource, $query);
      // Reset limit and skip so we get the full count
      $this->_limit = NULL;
      $this->_skip = NULL;
      $num_rows = $this->count_all_results($this->_collection, TRUE);
      $cursor->setTypeMap(array('array' => 'array'));
    }
    $this->reset_query();
    return new Cingo_result($cursor, $num_rows);
  }


  protected function _validate_operator($operator) {
    if (!empty($operator)) {
      if (substr($operator, 0, 1) != '$') {
        $operator = '$' . $operator;
      }
      if (!in_array($operator, $this->operators)) {
        trigger_error($operator . ' is not a valid operator', E_USER_ERROR);
      }
    }
    return $operator;
  }

  protected function _validate_wildcard($wildcard) {
    $before = '';
    $after = '';
    switch ($wildcard) {
      case 'before':
        $after = '$';
        break;
      case 'after':
        $before = '^';
        break;
      case 'both':
        break;
      case 'none':
        return FALSE;
        break;
      default:
        trigger_error('Invalid wildcard: ' . $wildcard, E_USER_WARNING);
    }
    return array('before' => $before, 'after' => $after);
  }

}
?>
