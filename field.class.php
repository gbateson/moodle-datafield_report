<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Class to represent a "datafield_report" field
 *
 * this field acts as an extra API layer to restrict view and
 * edit access to any other type of field in a database activity
 *
 * @package    data
 * @subpackage datafield_report
 * @copyright  2015 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

// prevent direct access to this script
defined('MOODLE_INTERNAL') || die();

// get required files
require_once($CFG->dirroot.'/mod/data/field/admin/field.class.php');

class data_field_report extends data_field_base {
    var $type = 'report';

    // param1: select field: SQL_FUNCTION(database.selectfield)
    // param2: condition: (database.wherefield OPERATOR field_or_value)
    // param3: sort field: database.sortfield

    // SQL_FUNCTION is one of the following aggregate functions:
    //      MIN, MAX, SUM, AVERAGE, COUNT, DISTINCT, JOIN, VALUE

    // "database." is optional.
    //      If numeric, it is assumed to be the cmid of another data activity on this site.
    //      Otherwise, it is assumed to be a table in the Moodle database,
    //      in which case, only certain table+field combinations are allowed.
    //      We must ensure that user can only view data that would usually be accessibile to that user.

    // OPERATOR can be one of the following:
    //      =, >, >=, <, <=, <>, !=, IN, NOT IN, LIKE, NOT LIKE, REGEXP

    // field_or_value can be another field in the current database
    //      it can of course possible another report field

    // certain aliases are available:
    //      MY_USERID, MY_COURSEID, MY_DATAID, MY_RECORDID

    // MY_RECORD_IDS(): SELECT DISTINCT id FROM {data_records} WHERE userid = MY_USERID AND dataid = MY_DATAID;
    // MY_RECORD_IDS(somedataid): SELECT DISTINCT id FROM {data_records} WHERE userid = MY_USERID AND dataid = somedataid;

    // MY_GROUP_IDS(): SELECT DISTINCT groupid FROM {groups_members} WHERE userid = MY_USERID AND courseid = MY_COURSEID;
    // MY_GROUP_IDS("group name"): SELECT id FROM {groups} WHERE name = "group name" AND courseid = MY_COURSEID;

    // MY_GROUP_USERIDS(): SELECT userid FROM mdl_groups_members WHERE groupid IN MY_GROUP_IDS()
    // MY_GROUP_USERIDS("group name"): SELECT userid FROM mdl_groups_members WHERE groupid IN MY_GROUP_IDS("group name")

    // MENU(USERS("Firstname LASTNAME", MY_GROUP_USERIDS()))

    var $teachers = null;

    const REGEXP_FUNCTION_START = '/^\s*(\w+)\s*\(/s';
    const REGEXP_FUNCTION_END = '/^\s*\)/s';
    const REGEXP_QUOTED_STRING = '/^\s*"([^"]*)"/s';
    const REGEXP_CONSTANT = '/^\s*([A-Z][A-Z0-9_-]*)\s*(?=,|\)|$)/s';
    const REGEXP_INTEGER = '/^\s*([0-9]+)\s*(?=,|\)|$)/s';
    const REGEXP_STRING = '/\s*(.*?[^)])\s*$/s';
    const REGEXP_COMMA = '/^\s*,/s';

    /**
     * Display the settings for this field on the "Fields" page
     *
     * @return void, but output is echo'd to browser
     */
    function display_edit_field() {
        data_field_admin::check_lang_strings($this);
        parent::display_edit_field();
    }

    /**
     * Format a report field for display in the "add/edit" template
     */
    function display_add_field($recordid = 0, $formdata = NULL) {
        return $this->display_browse_field($recordid, 'addtemplate');
    }

    /**
     * display content for this field from a user record
     * on the "View list" or "View single" page
     */
    function display_browse_field($recordid, $template) {
        switch ($template) {
            case 'listtemplate': $param = 'param1'; break;
            case 'singletemplate': $param = 'param2'; break;
            case 'addtemplate': $param = 'param3'; break;
            default: return ''; // shouldn't happen !!
        }
        if ($param) {
            if ($arguments = $this->parse_arguments($recordid, $this->field->$param, 0)) {
                list($arguments, $offset) = $arguments;
                foreach ($arguments as $a => $argument) {
                    $arguments[$a] = $this->compute($recordid, $argument);
                }
                return implode('', $arguments);
            } else {
                return 'no arguments';   
            }
        }
    }

    /**
     * display a form element for this field on the "Search" page
     *
     * @return HTML to send to browser
     */
    function display_search_field() {
        return 'Report field: '.$this->field->name;
    }

    /**
     * generate HTML to display icon for this field type on the "Fields" page
     */
    function image() {
        return data_field_admin::field_icon($this);
    }

    /**
     * text export is not supported for "report" fields
     */
    function text_export_supported() {
        return false;
    }

    /**
     * text export is not supported for "report" fields
     */
    function export_text_value($record) {
        return '';
    }

    /**
     * delete content associated with a report field
     * when the field is deleted from the "Fields" page
     */
    function delete_content($recordid=0) {
        data_field_admin::delete_content_files($this);
        return parent::delete_content($recordid);
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of config parameters
     * @since Moodle 3.3
     */
    public function get_config_for_external() {
    	return data_field_admin::get_field_params($this->field);
    }

    /////////////////////////////////////////////
    // methods to parse function strings
    /////////////////////////////////////////////

    /**
     * parse_arguments
     *
     * @param string $text
     * @param integer $offset
     * @return mixed array(array, integer)
     */
    protected function parse_arguments($recordid, $text, $offset) {
        $strlen = strlen($text);
        $arguments = array();
        $loop = true;
        while ($loop && ($offset < $strlen)) {
            $loop = false;
            if ($argument = $this->parse_argument($recordid, $text, $offset)) {
                list($argument, $offset) = $argument;
                $arguments[] = $argument;
                if (preg_match(self::REGEXP_COMMA, substr($text, $offset), $match)) {
                    $offset += strlen($match[0]);
                    $loop = true;
                }
            }
        }
        return array($arguments, $offset);
    }

    /**
     * parse_argument
     *
     * @param string $text
     * @param integer $offset
     * @return mixed array(mixed, integer) if successful; otherwise FALSE.
     */
    protected function parse_argument($recordid, $text, $offset) {
        if (preg_match(self::REGEXP_QUOTED_STRING, substr($text, $offset), $match)) {
            $argument = (object)array('type' => 'string',
                                      'value' => $match[1]);
            $offset += strlen($match[0]);
            return array($argument, $offset);
        }
        if (preg_match(self::REGEXP_CONSTANT, substr($text, $offset), $match)) {
            $argument = (object)array('type' => 'constant',
                                      'value' => $match[1]);
            $offset += strlen($match[0]);
            return array($argument, $offset);
        }
        if (preg_match(self::REGEXP_INTEGER, substr($text, $offset), $match)) {
            $argument = (object)array('type' => 'integer',
                                      'value' => $match[1]);
            $offset += strlen($match[0]);
            return array($argument, $offset);
        }
        if (preg_match(self::REGEXP_STRING, substr($text, $offset), $match)) {
            $argument = (object)array('type' => 'string',
                                      'value' => $match[1]);
            $offset += strlen($match[0]);
            return array($argument, $offset);
        }
        return $this->parse_function($recordid, $text, $offset);
    }

    /**
     * parse_function
     *
     * @param string $text
     * @param integer $offset
     * @return mixed array(object, integer) if successful; otherwise FALSE.
     */
    protected function parse_function($recordid, $text, $offset) {
        $name = '';
        $arguments = array();

        if (! preg_match(self::REGEXP_FUNCTION_START, substr($text, $offset), $match)) {
            return false;
        }

        $name = $match[1];
        $offset += strlen($match[0]);
        list($arguments, $offset) = $this->parse_arguments($recordid, $text, $offset);

        if (! preg_match(self::REGEXP_FUNCTION_END, substr($text, $offset), $match)) {
            return false;
        }

        $offset += strlen($match[0]);
        $function = (object)array('type' => 'function',
                                  'name' => $name,
                                  'arguments' => $arguments);
        return array($function, $offset);
    }

    /////////////////////////////////////////////
    // methods to compute functions and arguments
    /////////////////////////////////////////////

    /**
     * compute
     *
     * @param object $argument
     * @return mixed computer value of $argument
     */
    protected function compute($recordid, $argument) {
        global $USER;
        if (is_object($argument) && property_exists($argument, 'type')) {
            if ($argument->type == 'function') {
                if (property_exists($argument, 'name') == false || preg_match('/^\w+$/', $argument->name) == false) {
                    return get_string('errorunknownfunction', 'datafield_report', $argument->name);
                }
                if (property_exists($argument, 'arguments') == false || is_array($argument->arguments) == false) {
                    return "Oops, arguments are missing for function $argument->name";
                }
                $method = 'compute_'.strtolower($argument->name);
                if (method_exists($this, $method) == false) {
                    return get_string('errorunknownfunction', 'datafield_report', $argument->name);
                }
                return $this->$method($recordid, $argument->arguments);
            }
            if ($argument->type == 'string' ||
                $argument->type == 'integer') {
                return $argument->value;
            }
            if ($argument->type == 'constant') {
                switch ($argument->value) {
                    case 'CURRENT_USER': return $USER->id; break;
                    case 'CURRENT_RECORD': return $recordid; break;
                    case 'CURRENT_DATABASE': return $this->data->id; break;
                    case 'CURRENT_COURSE': return $this->data->course; break;
                    default: return 'Unknown constant: '.$argument->value;
                }
            }
            return 'Unkonwn argument type: '.$argument->type;
        }
        if (is_string($argument)) {
            return $argument; // shouldn't happen !!
        }
        return '';
    }

    /**
     * compute_get_database
     * GET_DATABASE(database)
     * "database" can be one of the following:
     *   a database id number
     *   d=99 a database id number
     *   id=99 a course module id number
     *   cmid=99 a course module id number
     *   otherwise, a string that matches the name of database in the current course.
     *
     * @param array $arguments
     * @param integer $recordid
     * @return integer a single dataid
     */
    protected function compute_get_database($recordid, $arguments) {
        if (empty($arguments)) {
            return $this->data->id; // id of current database
        }
        $database = array_shift($arguments);
        $database = $this->compute($recordid, $database);
        return $this->valid_dataid($database);
    }

    /**
     * compute_get_field
     * GET_FIELD(field, database)
     * "field" is one of the following:
     *   the numeric id of a field
     *   a string that matches the name of a field
     *
     * @return  a single fieldid
     *
     * @param array $arguments
     * @param integer $recordid
     * @return string
     */
    protected function compute_get_field($recordid, $arguments) {
        if (empty($arguments)) {
            return null; // shouldn't happen
        }

        $field = array_shift($arguments);
        $field = $this->compute($recordid, $field);
        if (empty($field)) {
            return null; // shouldn't happen
        }

        $database = array_shift($arguments);
        $database = $this->compute($recordid, $database);
        if (empty($database)) {
            $database = $this->data->id;
        }

        return $this->valid_fieldid($field, $database);
    }

    /**
     * compute_get_record
     * GET_RECORD(database, user)
     *
     * @param array $arguments
     * @param integer $recordid
     * @return integer
     */
    protected function compute_get_record($recordid, $arguments) {
        return $this->compute_get_records($recordid, $arguments, false);
    }

    /**
     * compute_get_records
     * GET_RECORD(database, users)
     *
     * @param array $arguments
     * @param integer $recordid
     * @return array
     */
    protected function compute_get_records($recordid, $arguments, $multiple=true) {
        global $DB, $USER;

        $database = array_shift($arguments);
        $database = $this->compute($recordid, $database);
        if (empty($database)) {
            $database = $this->data->id;
        }

        $users = array_shift($arguments);
        $users = $this->compute($recordid, $users);
        if (empty($users)) {
            $users = array($USER->id);
        } if (is_scalar($users)) {
            $users = array($users);
        }

        return $this->valid_recordids($database, $users, $multiple);
    }

    /**
     * compute_get_record
     * GET_VALUE(field, record)
     *
     * @param array $arguments
     * @param integer $recordid
     * @return integer
     */
    protected function compute_get_value($recordid, $arguments) {
        return $this->compute_get_values($recordid, $arguments, false);
    }

    /**
     * compute_get_values
     * GET_VALUE(field, records)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param boolean $multiple
     * @return integer
     */
    protected function compute_get_values($recordid, $arguments, $multiple=true) {
        global $DB;

        $field = array_shift($arguments);
        $field = $this->compute($recordid, $field);
        if (empty($field)) {
            return '';
        }

        $records = array_shift($arguments);
        $records = $this->compute($recordid, $records);
        if (empty($records)) {
            return '';
        }

        $dataid = $this->valid_dataid_from_recordids($records);
        $fieldid = $this->valid_fieldid($field, $dataid);

        list($select, $params) = $DB->get_in_or_equal($records);
        $select = "fieldid = ? AND recordid $select";
        array_unshift($params, $fieldid);

        if ($values = $DB->get_records_select_menu('data_content', $select, $params, 'id', 'id,content')) {
            if ($multiple) {
                return $values;
            } else {
                return reset($values);
            }
        }

        // Oops, no record found :-(
        if ($multiple) {
            return array();
        } else {
            return '';
        }
    }

    /**
     * compute_get_group
     * GET_GROUP(group)
     *
     * @param array $arguments
     * @param integer $recordid
     * @return integer
     */
    protected function compute_get_group($recordid, $arguments) {
        return $this->compute_get_groups($recordid, $arguments, false);
    }

    /**
     * compute_get_groups
     * GET_GROUPS(groups)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param boolean $multiple
     * @return integer
     */
    protected function compute_get_groups($recordid, $arguments, $multiple=true) {
        $groups = array_shift($arguments);
        $groups = $this->compute($recordid, $groups);
        return $this->valid_groupids($groups, $multiple);
    }

    /**
     * get_sql_like
     *
     * @param string $text
     * @return string
     */
    protected function get_sql_like($name, $text) {
        global $DB;

        $first = substr($text, 0, 1);
        $last = substr($text, -1);

        // Regular Expression syntax ^...$
        if ($first == '^' && $last == '$') {
            $text = substr($text, 1, -1);
        } else if ($first == '^') {
            $text = substr($text, 1).'%';
        } else if ($last == '$') {
            $text = '%'.substr($text, 0, -1);
        }

        // Simple pattern syntax where * means "zero or more chars"
        if ($first == '*') {
            $text = '%'.substr($text, 1);
        }
        if ($last == '*') {
            $text = substr($text, 0, -1).'%';
        }

        if (strpos($text, '%') === false) {
            $select = "$name = ?";
        } else {
            $select = $DB->sql_like($name, '?');
        }
        return array($select, array($text));
    }

    /**
     * valid_dataid
     *
     * @param mixed $database
     * @param integer $dataid
     * @return string
     */
    protected function valid_dataid($database) {
        global $DB;
        static $dataids = array();

        if (! array_key_exists($database, $dataids)) {

            $cm = null;
            switch (true) {
                case preg_match('/^[0-9]+$/', $database):
                    $cm = get_coursemodule_from_instance('data', $database);
                    break;

                case substr($database, 0, 2) == 'd=':
                    $cm = get_coursemodule_from_instance('data', substr($database, 2));
                    break;

                case substr($database, 0, 3) == 'id=':
                    $cm = get_coursemodule_from_id('data', substr($database, 3));
                    break;

                case substr($database, 0, 5) == 'cmid=':
                    $cm = get_coursemodule_from_id('data', substr($database, 5));
                    break;

                default:
                    list($select, $params) = $this->get_sql_like('name', $database);
                    $select = "course = ? AND $select";
                    array_unshift($params, $this->data->course);
                    if ($data = $DB->get_records_select('data', $select, $params, 'id', 'id,course,name')) {
                        $data = array_shift($data); // the oldest matching db
                        $cm = get_coursemodule_from_instance('data', $data->id);
                    }
            }

            if ($cm && \core_availability\info_module::is_user_visible($cm)) {
                // user has access to the specified database on this site.
                $dataids[$database] = $cm->instance;
            } else {
                $dataids[$database] = null;
            }
        }

        return $dataids[$database];
    }

    protected function valid_dataid_from_recordids($records) {
        global $DB;
        if (is_scalar($records)) {
            $records = array($records);
        }
        if (is_array($records) && count($records)) {
            list($select, $params) = $DB->get_in_or_equal($records);
            if ($records = $DB->get_records_select('data_records', "id $select", $params)) {
                return $this->valid_dataid(reset($records)->dataid);
            }
        }
        return '';
    }
    /**
     * valid_fieldid
     *
     * @param mixed $field
     * @param mixed $database
     * @return mixed fieldid OR null
     */
    protected function valid_fieldid($field, $database) {
        global $DB;
        static $fieldids = array();

        $dataid = $this->valid_dataid($database);

        if (! array_key_exists($dataid, $fieldids)) {
            $fieldids[$dataid] = array();
        }

        if (! array_key_exists($field, $fieldids[$dataid])) {
            if (preg_match('/^[0-9]+$/', $field)) {
                $params = array('id' => $field, 'dataid' => $dataid);
                $fieldids[$dataid][$field] = $DB->get_field('data_fields', 'id', $params);
            } else {
                list($select, $params) = $this->get_sql_like('name', $field);
                $select = "dataid = ? AND $select";
                array_unshift($params, $dataid);
                $fieldids[$dataid][$field] = $DB->get_field_select('data_fields', 'id', $select, $params);
            }
        }

        return $fieldids[$dataid][$field];
    }

    /**
     * valid_recordids
     *
     * @param mixed $field
     * @param mixed $database
     * @param boolean $multiple
     * @return mixed fieldid OR null
     */
    protected function valid_recordids($database, $users, $multiple) {
        global $DB;

        $dataid = $this->valid_dataid($database);
        $userids = $this->valid_userids($database, $users);

        $ids = array_intersect($users, $userids);
        if (count($ids)) {

            list($select, $params) = $DB->get_in_or_equal($ids);
            $select = "dataid = ? AND userid $select";
            array_unshift($params, $dataid);

            if ($records = $DB->get_records_select('data_records', $select, $params, 'id', 'id,userid')) {
                if ($multiple) {
                    return array_keys($records);
                } else {
                    return key($records);
                }
            }
        }

        // Oops, no record found :-(
        if ($multiple) {
            return array();
        } else {
            return '';
        }
    }

    protected function valid_userids($database, $users) {
        global $DB, $USER;
        static $userids = array();

        $dataid = $this->valid_dataid($database);

        if (! array_key_exists($dataid, $userids)) {
            $userids[$dataid] = array();

            if ($dataid) {
                $select = 'c.*';
                $from   = '{data} d, {course} c';
                $where  = 'd.id = ? AND d.course = c.id';
                $params = array($dataid);
                $course = $DB->get_record_sql("SELECT $select FROM $from WHERE $where", $params);
            } else {
                $course = null;
            }


            if ($course) {

                $cm = get_coursemodule_from_instance('data', $dataid);
                $context = context_module::instance($cm->id);

                if ($course->groupmodeforce) {
                    $groupmode = $course->groupmode;
                } else {
                    $groupmode = $cm->groupmode;
                }
                if ($groupmode == NOGROUPS || $groupmode == VISIBLEGROUPS) {
                    $accessallusers = true;
                } else {
                    // $GROUPMODE == SEPARATEGROUPS
                    $accessallusers = has_capability('moodle/site:accessallgroups', $context);
                }
                if ($accessallusers) {
                    $userids[$dataid] = get_enrolled_users($context);
                    $userids[$dataid] = array_keys($userids[$dataid]);
                } else {
                    if ($groups = groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid)) {
                        list($select, $params) = $DB->get_in_or_equal(array_keys($groups));
                        $userids[$dataid] = $DB->get_records_select_menu('groups_members', "groupid $select", $params, 'id', 'id,userid');
                        $userids[$dataid] = array_unique($userids[$dataid]);
                        $userids[$dataid] = array_values($userids[$dataid]);
                    }
                }
            }
        }

        return $userids[$dataid];
    }

    protected function valid_groupids($groups, $multiple) {
        global $DB;
        static $groupids = array();

        if (! array_key_exists($groups, $groupids)) {
            $groupids[$groups] = array();

            if (preg_match('/^[0-9]+$/', $groups)) {
                $params = array('id' => $groups, 'course' => $this->data->course);
                if ($records = $DB->get_records('groups', $params, 'id', 'id,course')) {
                    $groupids[$groups] = array_keys($records);
                }
            } else {
                list($select, $params) = $this->get_sql_like('name', $groups);
                $select = "course = ? AND $select";
                array_unshift($params, $this->data->course);
                if ($records = $DB->get_records_select('groups', $select, $params, 'id', 'id,course')) {
                    $groupids[$groups] = array_keys($records);
                }
            }
        }

        if ($multiple) {
            return $groupids[$groups];
        } else {
            return reset($groupids[$groups]);
        }
    }


    /**
     * compute_menu
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_menu($recordid, $arguments) {
        global $DB;
        $output = '';
        if (is_array($arguments) && isset($arguments[0]) && is_object($arguments[0])) {
            $fieldid = $this->field->id;
            $formfieldname = 'field_'.$fieldid;
            if ($recordid) {
                $params = array('fieldid' => $fieldid,
                                'recordid' => $recordid);
                $value = $DB->get_field('data_content', 'content', $params);
            } else {
                $value = '';
            }
            switch ($arguments[0]->type) {
                case 'function':
                    $argument = $this->compute($recordid, $arguments[0]);
                    if (is_array($argument) && count($argument)) {
                        $output = html_writer::select($argument, $formfieldname, $value);
                    } else {
                        $output = "$argument";
                    }
                    break;
                case 'string':
                case 'number':
                    $argument = $arguments[0]->value;
                    $argument = explode(',', $argument);
                    $argument = array_filter($argument);
                    if (count($argument) <= 1) {
                        $argument = implode('', $argument);
                        $params = array('type' => 'hidden', 'value' => $argument);
                        $output = html_writer::empty_tag('input', $formfieldname, $params).$argument;
                    } else {
                        $output = html_writer::select($argument, $formfieldname, $value);
                    }
            }
        }
        return $output;
    }

    /**
     * compute_users
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_users($recordid, $arguments, $multiple=true) {
        global $DB;
        $output = '';
        if (is_array($arguments) && count($arguments) == 2) {
            list($format, $userids) = $arguments;

            $format = $this->compute($recordid, $format);
            if ($format == '') {
                $format = 'default';
            }

            if ($userids = $this->compute($recordid, $userids)) {
                if ($multiple) {
                    if (is_scalar($userids)) {
                        $userids = array($userids);
                    }
                    list($select, $params) = $DB->get_in_or_equal($userids);
                    if ($users = $DB->get_records_select('user', "id $select", $params)) {
                        $output = array();
                        foreach ($users as $user) {
                            $output[$user->id] = $this->format_user_name($format, $user);
                        }
                        if ($limitto == 1) {
                            $output = reset($output);
                        }
                    }
                } else {
                    if (is_scalar($userids)) {
                        $userid = intval($userids);
                    } else {
                        $userid = intval(reset($userids));
                    }
                    if ($user = $DB->get_record('user', array('id' => $userid))) {
                        $output = $this->format_user_name($format, $user);
                    }
                }
            }
        } else {
            $a = (object)array(
                'name' => 'USERS',
                'count' => 2,
                'description' => get_string('errorfunctionusers', 'datafield_report')
            );
            $output = get_string('errorfunctionarguments', 'datafield_report', $a);
        }
        return $output;
    }

    /**
     * compute_users
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_user($recordid, $arguments) {
        return $this->compute_users($recordid, $arguments, false);
    }

    protected function format_user_name($format, $user) {
        static $search = '/firstnamephonetic|lastnamephonetic|'.
                         'firstname|middlename|lastname|'.
                         'alternatename/i';

        if ($format == 'default') {
            return fullname($user);
        }
        return preg_replace_callback(
            $search,
            function($matches) use ($user) {
                $match = $matches[0];
                $name = strtolower($match);
                if (isset($user->$name)) {
                    $name = $user->$name;
                    switch (true) {
                        case preg_match('/^[A-Z]+$/', $match):
                            $name = strtoupper($name);
                            break;
                        case preg_match('/^[a-z]+$/', $match):
                            $name = strtolower($name);
                            break;
                        case preg_match('/^[A-Z][a-z]+$/', $match):
                            $name = ucwords($name);
                            break;
                    }
                }
                return $name;
            },
            $format
        );
    }

    /**
     * compute_my_group_userids
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_my_group_userids($recordid, $arguments) {
        global $DB;
        $output = '';

        $select = '';
        if (empty($arguments[0])) {
            $groups = groups_get_activity_allowed_groups($this->cm);
            if (is_array($groups) && count($groups)) {
                list($select, $params) = $DB->get_in_or_equal(array_keys($groups));
            }
        } else {
            switch (true) {

                case $arguments[0]->type == 'string':
                    $params = array('name' => $arguments[0]->value,
                                    'course' => $this->data->course);
                    $groupid = $DB->get_field('groups', 'id', $params);
                    break;

                case $arguments[0]->type == 'integer':
                    $params = array('id' => $arguments[0]->value,
                                    'course' => $this->data->course);
                    $groupid = $DB->get_field('groups', 'id', $params);
                    break;

                default:
                    $groupid = 0; // $this->compute($recordid, $argument[0])
            }
            if ($groupid) {
                $select = '= ?';
                $params = array($groupid);
            }
        }
        if ($select) {
            if ($users = $DB->get_records_select('groups_members', "groupid $select", $params, 'id', 'DISTINCT id, userid')) {
                if ($teachers = $this->get_teachers()) {
                    foreach ($teachers as $teacher) {
                        unset($users[$teacher->id]);
                    }
                }
                if (count($users)) {
                    $output = array_keys($users);
                }
            }
        }
        return $output;
    }

    /**
     * get list of teachers (including non-editing teachers) in this course
     */
    protected function get_teachers() {
        if ($this->teachers === null) {
            $this->teachers = get_users_by_capability($this->context, 'mod/data:manageentries', 'u.id,u.username');
        }
        return $this->teachers;
    }

    /**
     * compute_fieldvalue
     *
     * @param array $arguments[fieldname, db_identifier]
     * @return string
     */
    protected function compute_fieldvalue($recordid, $arguments) {
        global $DB, $USER;

        $cm = null;
        $data = null;
        $userids = null;

        if (is_array($arguments)) {
            if ($fieldname = array_shift($arguments)) {
                $fieldname = $fieldname->value;
            }
            if ($cm = array_shift($arguments)) {
                if ($cm = $cm->value) {
                    switch (true) {
                        case substr($cm, 0, 5) == 'cmid=':
                            $cm = get_coursemodule_from_id('data', (int)substr($cm, 5));
                            break;
                        case substr($cm, 0, 2) == 'd=':
                            $cm = get_coursemodule_from_instance('data', (int)substr($cm, 2));
                            break;
                        default:
                            $params = array('course' => $this->data_course,
                                            'name' => $cm);
                            if ($data = $DB->get_records('data', $params, 'id')) {
                                $data = array_shift($data); // the oldest matching db
                                $cm = get_coursemodule_from_instance('data', $data->id);
                            } else {
                                $cm = '';
                            }
                    }
                    if ($cm && \core_availability\info_module::is_user_visible($cm)) {
                        // user has acces to the specified database on this site.
                    } else {
                        $cm = '';
                    }
                }
            } else {
                $cm = $this->cm;
                $data = $this->data;
            }
            if ($userids = array_shift($arguments)) {
                $userids = $this->compute($recordid, $userids);
                $userids = explode($userids);
                $userids = array_filter($userids);
            } else  {
                $userids = array($USER->id);
            }

            if ($cm && $fieldname && $userids && count($userids)) {
                if (empty($data)) {
                    $data = $DB->get_record('data', array('id' => $cm->instance));
                }
                if ($field = data_get_field_from_name($fieldname, $data)) {
                    list($select, $params) = $DB->get_in_or_equal($userids);
                    array_unshift($params, $data->id);
                    if ($records = $DB->get_records_select('data_records', "dataid = ? AND userid $select", $params, 'userid,id', 'id,userid')) {
                        $output = array();
                        foreach ($records as $record) {
                            $output[] = $record->id.': '.$field->display_browse_field($record->id, 'singletemplate');
                        }
                        return implode(',', $output);
                    }
                }
            }
        }
        return '';
    }
}
