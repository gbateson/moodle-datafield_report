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
        if ($function = $this->parse_function($this->field->param1, 0)) {
            list($function, $offset) = $function;
            return $this->compute($function);
        }
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
     * parse_function
     *
     * @param string $text
     * @param integer $offset
     * @return mixed array(object, integer) if successful; otherwise FALSE.
     */
    protected function parse_function($text, $offset) {
        $name = '';
        $arguments = array();

        if (! preg_match(self::REGEXP_FUNCTION_START, substr($text, $offset), $match)) {
            return false;
        }

        $name = $match[1];
        $offset += strlen($match[0]);
        list($arguments, $offset) = $this->parse_arguments($text, $offset);

        if (! preg_match(self::REGEXP_FUNCTION_END, substr($text, $offset), $match)) {
            return false;
        }

        $offset += strlen($match[0]);
        $function = (object)array('type' => 'function',
                                  'name' => $name,
                                  'arguments' => $arguments);
        return array($function, $offset);
    }

    /**
     * parse_arguments
     *
     * @param string $text
     * @param integer $offset
     * @return mixed array(array, integer)
     */
    protected function parse_arguments($text, $offset) {
        $strlen = strlen($text);
        $arguments = array();
        $loop = true;
        while ($loop && ($offset < $strlen)) {
            $loop = false;
            if ($argument = $this->parse_argument($text, $offset)) {
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
    protected function parse_argument($text, $offset) {
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
        return $this->parse_function($text, $offset);
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
    protected function compute($argument) {
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
                return $this->$method($argument->arguments);
            }
            if ($argument->type == 'string' || $argument->type == 'number') {
                return $argument->value;
            }
        }
        if (is_string($argument)) {
            return $argument; // shouldn't happen !!
        }
        return '';
    }

    /**
     * compute_menu
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_menu($arguments) {
        $output = '';
        if (is_array($arguments) && isset($arguments[0]) && is_object($arguments[0])) {
            switch ($arguments[0]->type) {
                case 'function':
                    $argument = $this->compute($arguments[0]);
                    if (is_array($argument) && count($argument)) {
                        $output = html_writer::select($argument, $this->field->name);
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
                        $output = html_writer::empty_tag('input', $this->field->name, $params).$argument;
                    } else {
                        $output = html_writer::select($argument, $this->field->name);
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
    protected function compute_users($arguments) {
        global $DB;
        $output = '';
        if (is_array($arguments) && count($arguments) == 2) {
            list($format, $userids) = $arguments;

            $format = $this->compute($format);
            if ($format == '') {
                $format = 'default';
            }

            if ($userids = $this->compute($userids)) {
                list($select, $params) = $DB->get_in_or_equal($userids);
                if ($users = $DB->get_records_select('user', "id $select", $params)) {
                    $output = array();
                    foreach ($users as $user) {
                        $output[$user->id] = $this->format_user_name($format, $user);
                    }
                }
            }
        } else {
            $a = (object)array(
                'name' => 'USERS',
                'count' => 2,
                'description' => get_string('errorfunctionusersdescription', 'datafield_report')
            );
            $output = get_string('errorfunctionarguments', 'datafield_report', $a);
        }
        return $output;
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
     * compute_users
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_my_group_userids($arguments) {
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
                    $groupid = 0; // $this->compute($argument[0])
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
     * compute_users
     *
     * @param array $arguments[fieldname, db_identifier]
     * @return string
     */
    protected function compute_fieldvalue($arguments) {
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
                $userids = $this->compute($userids);
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
