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

    // param1: format for addtemplaate and asearchtemplate
    // param2: format for listtemplate and singletemplate
    // param3: extra format string
    // param4: extra format string
    // param5: optional field to signify how to handle values imported during an "Import entries" operation
    //         default - the default action, i.e. the value will be stored as it is, without any processing
    //         ignore - imported value should be ignored (i.e. data_content.content will be set to NULL)
    //         username - imported value is a username and will be converted to a userid
    //         fullnameuser - imported value is a user's fullname and will be converted to a userid
    //         groupname - imported value is the name of an group and will be converted to a group id
    //         activityname - imported value is the name of an activity and will be converted to a course module id
    //         coursename - imported value is the name of course and will be converted to a course id

    var $studentids = null;
    var $teacherids = null;

    const REGEXP_FUNCTION_START = '/^\s*(\w+)\s*\(/s';
    const REGEXP_FUNCTION_END = '/^\s*\)/s';
    //const REGEXP_QUOTED_STRING = '/^\s*"([^"]*)"/s';
    const REGEXP_QUOTED_STRING = '/^\s*(["\'])(.*?)\1/s';
    const REGEXP_CONSTANT = '/^\s*([A-Z][A-Z0-9_-]*)\s*(?=,|\)|$)/s';
    const REGEXP_INTEGER = '/^\s*([0-9]+)\s*(?=,|\)|$)/s';
    const REGEXP_STRING = '/\s*(.*?[^)])\s*$/s';
    const REGEXP_COMMA = '/^\s*,/s';

    const HIRAGANA_STRING = '/^[ \x{3000}-\x{303F}\x{3040}-\x{309F}]+$/u';
    const KATAKANA_FULL_STRING = '/^[ \x{3000}-\x{303F}\x{30A0}-\x{30FF}]+$/u';
    const KATAKANA_HALF_STRING = '/^[ \x{3000}-\x{303F}\x{31F0}-\x{31FF}\x{FF61}-\x{FF9F}]+$/u';
    const ROMAJI_STRING = '/^( |(t?chi|s?shi|t?tsu)|((b?by|t?ch|hy|jy|k?ky|p?py|ry|s?sh|s?sy|w|y)[auo])|((b?b|d|f|g|h|j|k?k|m|n|p?p|r|s?s|t?t|z)[aiueo])|[aiueo]|[mn])+$/i';

    /**
     * generate HTML to display icon for this field type on the "Fields" page
     */
    function image() {
        return data_field_admin::field_icon($this);
    }

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
        return $this->display_field($recordid, $template);
    }

    /**
     * display content for this field from a user record
     */
    function display_field($recordid, $template) {
        switch ($template) {
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
                $param = 'param'.$template;
                break;

            case 'param1':
            case 'param2':
            case 'param3':
            case 'param4':
            case 'param5':
                $param = $template;
                break;

            case 'extra1':
                $param = 'param3';
                break;
            case 'extra2':
                $param = 'param4';
                break;
            case 'extra3':
                $param = 'param5';
                break;

            case 'add':
            case 'edit':
            case 'input':
            case 'addtemplate':
            case 'asearchtemplate':
                $param = 'param1';
                break;

            case '':
            case 'view':
            case 'list':
            case 'single':
            case 'output':
            case 'listtemplate':
            case 'singletemplate':
                $param = 'param2';
                break;

            default:
                // Format is not recognized.
                return $template;
        }
        if (substr($this->field->$param, 0, 8) == 'SAME_AS_') {
            switch (substr($this->field->$param, 8)) {
                case 'INPUT':
                case 'PARAM1':
                    $param = 'param1';
                    break;
                case 'OUTPUT':
                case 'PARAM2':
                    $param = 'param2';
                    break;
                case 'EXTRA1':
                case 'PARAM3':
                    $param = 'param3';
                    break;
                case 'EXTRA2':
                case 'PARAM4':
                    $param = 'param4';
                    break;
                case 'EXTRA3':
                case 'PARAM5':
                    $param = 'param5';
                    break;
            }
        }
        if (empty($this->field->$param)) {
            // Requested format is empty :-(
            return '';
        }
        if ($arguments = $this->parse_arguments($recordid, $this->field->$param, 0)) {
            list($arguments, $offset) = $arguments;
            foreach ($arguments as $a => $argument) {
                $argument = $this->compute($recordid, $argument);
                if (is_array($argument)) {
                    $msg =  get_string($template, 'data');
                    $msg = (object)array('template' =>$msg,
                                         'fieldname' => $this->field->name);
                    $msg = get_string('reducearrayresult', 'datafield_report', $msg);
                    $arguments[$a] = $msg.html_writer::alist($argument);
                } else {
                    $arguments[$a] = $argument;
                }
            }
            return implode('', $arguments);
        } else {
            return $this->field->$param;
        }
    }

    /**
     * display a form element for this field on the "Search" page
     *
     * @return HTML to send to browser
     */
    function display_search_field() {
        return $this->display_field(0, 'asearchtemplate');
    }

    /**
     * parse search field from "Search" page
     * (required by view.php)
     */
    function parse_search_field() {
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
     * get_sort_field
     * (required by view.php)
     */
    function get_sort_field() {
        return '';
    }

    /**
     * get_sort_sql
     * (required by view.php)
     */
    function get_sort_sql($fieldname) {
        return '';
    }

    /**
     * generate sql required for search page
     * Note: this function is missing from the parent class :-(
     */
    function generate_sql($tablealias, $value) {
        return '';
    }

    /**
     * export_text_value
     *
     */
    public function export_text_value($record) {
    	return data_field_admin::get_export_value($record->fieldid);
    }

    /**
     * update_content_import
     *
     * @param integer $recordid of newly added data record
     * @param string $value of this field for current record in CSV file
     * @param string $formfieldid e.g. "field_999", where "999" is the field id
     */
    public function update_content_import($recordid, $value, $formfieldid) {
        global $DB;

    	switch ($this->field->param5) {
    	    case 'default':
    	        // do nothing
    	        break;

    	    case 'ignore':
    	        $value = null;
    	        break;

    	    case 'username':
    	        $value = $DB->get_field('user', 'id', array('username' => $value));
    	        break;

    	    case 'fullnameuser':
    	        if (($pos1 = strrpos($value, '(')) && ($pos2 = strpos($value, ')', $pos1))) {
                    $value = trim(substr_replace($value, '', $pos1, $pos2 - $pos1));
    	        }

    	        $names = explode(' ', $value);
    	        $names = array_map('trim', $names);
    	        $names = array_filter($names);

    	        $name1 = ''; // first name
    	        $name2 = ''; // last name
    	        $value = '';
    	        while (empty($value) && count($names)) {
    	            if ($name2) {
                        $name1 .= ' '.array_shift($names);
    	            }
                    $name2 = implode(' ', $names);
                    $select = '((firstname = ? AND lastname = ?) OR '.
                               '(firstname = ? AND lastname = ?) OR '.
                               '(firstnamephonetic = ? AND lastnamephonetic = ?) OR '.
                               '(firstnamephonetic = ? AND lastnamephonetic = ?)) AND delete = ?';
                    $params = array($name1, $name2, $name2, $name1,
                                    $name1, $name2, $name2, $name1, 0);
                    if ($value = $DB->get_records_select('user', $select, $params, 'id', 'id,username')) {
                        $value = key($value); // get id of first matching user (we expect only one)
                        // TODO: filter users to only those enrolled in the current course?
                    }
    	        }
    	        break;

    	    case 'groupname':
    	        $params = array('course' => $this->data->course,
    	                        'name' => $value);
                $value = $DB->get_field('groups', 'id', $params);
    	        break;

    	    case 'activityname':
    	        $name = trim($value);
    	        $value = '';
                $modinfo = get_fast_modinfo($this->data->course);
                foreach ($modinfo->get_cms() as $cm) {
                    if ($name == $cm->name) {
                        $value = $cm->id;
                        break;
                    }
                }
    	        break;

    	    case 'coursename':
                $value = $DB->get_field('course', 'id', array('shortname' => $value));
    	        break;
    	}
        if ($value === false) {
            $value = '';
        }
        
        $params = array(
            'fieldid' => $this->field->id,
            'recordid' => $recordid
        );
        if ($content = $DB->get_record('data_content', $params)) {
            if (strcmp($content->content, $value)) {
                $content->content = $value;
                $DB->update_record('data_content', $content);
            }
        } else {
            $content = (object)$params;
            $content->content = $value;
            $content->id = $DB->insert_record('data_content', $content);
        }
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
                                      'value' => $match[2]);
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
     * @param mixed $default (optional, default = null)
     * @return mixed computer value of $argument
     */
    protected function compute($recordid, $argument, $default=null) {
        global $CFG, $DB, $USER;
        if ($argument === null && is_string($default)) {
            $argument = (object)array('type' => 'constant',
                                      'value' => $default);
        }
        if ($argument === null || is_string($argument)) {
            return $argument;
        }
        if (is_object($argument) && property_exists($argument, 'type')) {
            if ($argument->type == 'function') {
                if (property_exists($argument, 'name') == false || preg_match('/^\w+$/', $argument->name) == false) {
                    return get_string('errorunknownfunction', 'datafield_report', $argument->name);
                }
                if (property_exists($argument, 'arguments') == false || is_array($argument->arguments) == false) {
                    return "Oops, arguments are missing for function $argument->name";
                }
                $method = 'compute_'.strtolower($argument->name);
                if (method_exists($this, $method)) {
                    return $this->$method($recordid, $argument->arguments);
                } else {
                    die(get_string('errorunknownfunction', 'datafield_report', $argument->name));
                    return get_string('errorunknownfunction', 'datafield_report', $argument->name);
                }
            }
            if ($argument->type == 'string' || $argument->type == 'integer') {
                return $argument->value;
            }
            if ($argument->type == 'constant') {
                switch ($argument->value) {

                    case 'RECORD_USER':
                        if (empty($recordid)) {
                            return optional_param('uid', $USER->id, PARAM_INT);
                        } else {
                            return $DB->get_field('data_records', 'userid', array('id' => $recordid));
                        }

                    case 'CURRENT_USER':
                        return $USER->id;

                    case 'CURRENT_RECORD':
                        return $recordid;

                    case 'CURRENT_RECORDS':
                        $params = array('dataid' => $$this->data->id, 'userid' => $USER->id);
                        return $DB->get_records_menu('data_records', $params, 'id', 'id,userid');

                    case 'CURRENT_DATABASE':
                        return $this->data->id;

                    case 'CURRENT_COURSE':
                        return $this->data->course;

                    case 'CURRENT_GROUP':
                        return groups_get_activity_group($this->cm);

                    case 'CURRENT_GROUPS':
                        $groups = groups_get_activity_allowed_groups($this->cm);
                        return array_keys($groups);

                    case 'CURRENT_USERS':
                        return $this->valid_userids();

                    case 'CURRENT_STUDENTS':
                        return array_intersect($this->valid_userids(),
                                               $this->valid_studentids());

                    case 'CURRENT_TEACHERS':
                        return array_intersect($this->valid_userids(),
                                               $this->valid_teacherids());

                    case 'DEFAULT_NAME_FORMAT':
                        $format = '';
                        if (isset($CFG->fullnamedisplay)) {
                            $format = $CFG->fullnamedisplay;
                        }
                        if (empty($format) || $format == 'language') {
                            $format = get_string('fullnamedisplay');
                        }
                        return preg_replace('/\{\$a->(\w+)\}/', '$1', $format);

                    case 'NEXT_DATABASE':
                        return $this->find_cm($recordid, array(), 1);

                    case 'PREVIOUS_DATABASE':
                        return $this->find_cm($recordid, array(), -1);

                    default:
                        return 'Unknown constant: '.$argument->value;
                }
            }
            return 'Unknown argument type: '.$argument->type;
        }
        return null;
    }

    protected function compute_next($recordid, $arguments) {
        return $this->find_cm($recordid, $arguments, 1);
    }

    protected function compute_prev($recordid, $arguments) {
        return $this->find_cm($recordid, $arguments, -1);
    }

    protected function compute_previous($recordid, $arguments) {
        return $this->find_cm($recordid, $arguments, -1);
    }

    protected function find_cm($recordid, $arguments, $type) {
        global $CFG;

        static $plugins = null;
        if ($plugins === null) {
            if (class_exists('core_plugin_manager')) {
                // Moodle >= 2.6
                // "/lib/classes/plugin_manager.php" will be included automatically
                $plugins = core_plugin_manager::instance()->get_plugins_of_type('mod');
            } else if (class_exists('plugin_manager')) {
                // Moodle >= 2.1 - 2.5
                require_once($CFG->dirroot.'/lib/pluginlib.php');
                $plugins = plugin_manager::instance()->get_plugins();
                $plugins = $plugins['mod'];
            }
        }

        // We expect 2 arguments: a modname and an activityname.
        // Both are optional and we can accept them in any order.
        // i.e. all of the following are valid:
        //   PREVIOUS()
        //   PREVIOUS("data")
        //   PREVIOUS("data", "Project 1:*")
        //   PREVIOUS("Project 1:*")
        //   PREVIOUS("Project 1:*", "")
        //   PREVIOUS("Project 1:*", "data")
        //   PREVIOUS("", "data")
        //   PREVIOUS("", "Project 1:*")

        // Set the default values for the arguments.
        $modname = 'data';
        $activityname = '';

        // Override defaults with specified arguments, if any.
        for ($i=1; $i<=2; $i++) {
            if ($arg = $this->compute($recordid, array_shift($arguments))) {
                if (array_key_exists($arg, $plugins)) {
                    $modname = $arg;
                } else {
                    $activityname = $arg;
                }
            }
        }

        // Convert activity name to PREG pattern.
        if ($activityname) {
            $replacements = array('^' => 'START_OF_STRING',
                                  '*' => 'ANY_CHARS',
                                  '.' => 'SINGLE_CHARS',
                                  '$' => 'END_OF_STRING');
            $activityname = strtr($activityname, $replacements);

            $activityname = '/'.preg_quote($activityname, '/').'/u';

            $replacements = array('START_OF_STRING' => '^',
                                  'ANY_CHARS' => '.*',
                                  'SINGLE_CHARS' => '.',
                                  'END_OF_STRING' => '$');
            $activityname = strtr($activityname, $replacements);
        }

        $found = false;
        $prev = 0;

        $modinfo = get_fast_modinfo($this->data->course);
        foreach ($modinfo->get_cms() as $cmid => $cm) {
            if ($cm->id == $this->cm->id) {
                // this is the current database
                if ($type == -1) {
                    return $prev;
                }
                $found = true;
            } else if ($modname == '' || $cm->modname == $modname) {
                if ($activityname == '' || preg_match($activityname, $cm->name)) {
                    // this is a matching activity
                    if ($found == false) {
                        $prev = $cm->instance;
                    } else {
                        // We can only get here if we found a matching activity
                        // after the current database has been found, so we can
                        // just return the activity instance id (e.g. dataid).
                        return $cm->instance;
                    }
                }
            }
        }
        return 0; // We couldn't find a matching activity.
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
        if ($database = $this->compute($recordid, array_shift($arguments), 'CURRENT_DATABASE')) {
            return $this->valid_dataid($database);
        }
        return null;
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
        if ($field = $this->compute($recordid, array_shift($arguments))) {
            if ($database = $this->compute($recordid, array_shift($arguments), 'CURRENT_DATABASE')) {
                return $this->valid_fieldid($database, $field);
            }
        }
        return null;
    }

    /**
     * compute_get_record
     * GET_RECORD(database, field, value)
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
     * GET_RECORDS(database, field, value)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param boolean $multiple TRUE if we should return multiple results; otherwise FALSE
     * @return array
     */
    protected function compute_get_records($recordid, $arguments, $multiple=true) {
        if ($database = $this->compute($recordid, array_shift($arguments), 'CURRENT_DATABASE')) {
            if ($field = $this->compute($recordid, array_shift($arguments))) {
                $value = $this->compute($recordid, array_shift($arguments));
                return $this->valid_recordids($database, $field, $value, $multiple);
            }
        }
        return null;
    }

    /**
     * compute_get_user_record
     * GET_USER_RECORD(database, user)
     *
     * @param array $arguments
     * @param integer $recordid
     * @return integer
     */
    protected function compute_get_user_record($recordid, $arguments) {
        return $this->compute_get_user_records($recordid, $arguments, 'CURRENT_USER', false);
    }

    /**
     * compute_get_user_records
     * GET_USER_RECORDS(database, users)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param mixed $default users
     * @param boolean $multiple TRUE if we should return multiple results; otherwise FALSE
     * @return array
     */
    protected function compute_get_user_records($recordid, $arguments, $default='CURRENT_USERS', $multiple=true) {
        global $DB, $USER;
        if ($database = $this->compute($recordid, array_shift($arguments), 'CURRENT_DATABASE')) {
            if ($users = $this->compute($recordid, array_shift($arguments), $default)) {
                if (is_scalar($users)) {
                    $users = array($users);
                }
                return $this->valid_user_recordids($database, $users, $multiple);
            }
        }
        return null;
    }

    /**
     * compute_get_active_others
     * GET_ACTIVE_OTHERS(database, groups, countfield, menulength)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param mixed $default group
     * @return array
     */
    protected function compute_get_active_others($recordid, $arguments, $default='CURRENT_GROUP') {
        return $this->compute_get_active_users($recordid, $arguments, $default, true);
    }

    /**
     * compute_get_active_users
     * GET_ACTIVE_USERS(database, groups, countfield, menulength)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param mixed $default group
     * @param boolean $excludeme TRUE if current user should be excluded from result; otherwise FALSE
     * @return array
     */
    protected function compute_get_active_users($recordid, $arguments, $default='CURRENT_GROUP', $excludeme=false) {
        global $DB, $USER;
        // get the target database (typically, this is NOT the current database)
        if ($database = $this->compute($recordid, array_shift($arguments), 'CURRENT_DATABASE')) {

            $dataid = $this->valid_dataid($database);
            if ($groups = $this->compute($recordid, array_shift($arguments), $default)) {
                if (is_scalar($groups)) {
                    $groups = array($groups);
                } else {
                    $groups = array_filter($groups);
                }
            }
            if (empty($groups)) {
                $select = 'dataid = ?';
                $params = array($dataid);
            } else {
                list($select, $params) = $DB->get_in_or_equal($groups);
                if ($userids = $DB->get_records_select_menu('groups_members', "groupid $select", $params, 'id', 'id,userid')) {
                    list($select, $params) = $DB->get_in_or_equal($userids);
                    $select = "dataid = ? AND userid $select";
                    array_unshift($params, $dataid);
                } else {
                    // no members in the requested groups
                    $select = 'userid < ?';
                    $params = array(0);
                }
            }
            if ($excludeme) {
                $select .= ' AND userid <> ?';
                $params[] = $USER->id;
            }
            if ($userids = $DB->get_records_select_menu('data_records', $select, $params, 'id', 'id,userid')) {

                $dataid = $this->data->id; // the id of the current database
                $userids = $this->valid_userids($dataid, array_unique($userids));
                // $userids holds ids of users who have records in the target database
                // and who are accessible to the current user in the current database.

                // $countfield is the database field that holds the userid to which this record relates.
                // Note: this is different from the "userid" field in the "data_records" table.
                $countfield = $this->compute($recordid, array_shift($arguments), '');
                if ($countfield = $this->valid_fieldid($dataid, $countfield)) {

                    // $countremaining is the remaining number of items to be added to the menu
                    $countremaining = $this->compute($recordid, array_shift($arguments), 10);
                    $countremaining = intval($countremaining);

                    list($where, $params) = $DB->get_in_or_equal($userids);
                    $where  = "dc.fieldid = ? AND dc.content $where";
                    array_unshift($params, $countfield);

                    $select = 'dc.content AS contentvalue, COUNT(*) AS contentcount';
                    $from   = '{data_content} dc LEFT JOIN {data_records} dr ON dc.recordid = dr.id';
                    $group  = 'dc.content';
                    if ($ids = $DB->get_records_sql_menu("SELECT $select FROM $from WHERE $where GROUP BY $group", $params)) {
                        $userids = array(0 => array_fill_keys($userids, 0));
                        foreach ($ids as $id => $count) {
                            unset($userids[0][$id]);
                            $userids[$count][$id] = $count;
                        }
                        ksort($userids);

                        $menu = array();
                        foreach ($userids as $ids) {
                            if ($countremaining) {
                                $count = count($ids);
                                if ($countremaining >= $count) {
                                    // add all ids
                                    $ids = array_keys($ids);
                                    $countremaining -= $count;
                                } else if ($countremaining >= 1) {
                                    // add only a random subset of ids
                                    $ids = array_rand($ids, $countremaining);
                                    $countremaining = 0; // force end of loop
                                }
                                if (is_scalar($ids)) {
                                    // if $countremaining was 1, then "array_rand()"
                                    // returned a single scalar value - gotcha !!
                                    $menu[] = $ids;
                                } else {
                                    shuffle($ids);
                                    $menu = array_merge($menu, $ids);
                                }
                            }
                        }
                        $userids = $menu;
                    }
                }
                return $userids;
            }
        }
        return null;
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
        return $this->compute_get_values($recordid, $arguments, 'CURRENT_RECORD', false);
    }

    /**
     * compute_get_values
     * GET_VALUES(field, records)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param mized $default records
     * @param boolean $multiple TRUE if we should return multiple results; otherwise FALSE
     * @return integer
     */
    protected function compute_get_values($recordid, $arguments, $default='CURRENT_RECORDS', $multiple=true) {
        global $DB;

        if ($field = $this->compute($recordid, array_shift($arguments))) {
            if ($records = $this->compute($recordid, array_shift($arguments), $default)) {

                $dataid = $this->valid_dataid_from_recordids($records);
                $fieldid = $this->valid_fieldid($dataid, $field);

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
            }
        }

        // Oops, no record found :-(
        if ($multiple) {
            return array();
        } else {
            return null;
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
        return $this->compute_get_groups($recordid, $arguments, 'CURRENT_GROUP', false);
    }

    /**
     * compute_get_groups
     * GET_GROUPS(groups, course)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param mixed $default groups
     * @param boolean $multiple TRUE if we should return multiple results; otherwise FALSE
     * @return integer
     */
    protected function compute_get_groups($recordid, $arguments, $default='CURRENT_GROUPS', $multiple=true) {
        if ($groups = $this->compute($recordid, array_shift($arguments), $default)) {
            if ($course = $this->compute($recordid, array_shift($arguments), 'CURRENT_COURSE')) {
                return $this->valid_groupids($groups, $course, $multiple);
            }
        }
        return null;
    }

    /**
     * compute_get_group_users
     * GET_GROUP_USERS(group, course)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param boolean $multiple
     * @return integer
     */
    //protected function compute_get_group_users($recordid, $arguments, $multiple=true) {
    //    $groups = $this->compute($recordid, array_shift($arguments));
    //    $course = $this->compute($recordid, array_shift($arguments));
    //    return $this->valid_group_userids($course, $groups, $multiple);
    //}

    /**
     * compute_get_course_users
     * GET_COURSE_USERS(course)
     *
     * @param array $arguments
     * @param integer $recordid
     * @param boolean $multiple
     * @return integer
     */
    //protected function compute_get_course_users($recordid, $arguments, $multiple=true) {
    //    $course = $this->compute($recordid, array_shift($arguments));
    //    return $this->valid_course_userids($course, $multiple);
    //}

    /**
     * compute_my_group_userids
     * MY_GROUP_USERIDS()
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_my_group_userids($recordid, $arguments) {
        global $DB;
        $output = '';

        $select = '';
        if (empty($arguments)) {
            $groups = groups_get_activity_allowed_groups($this->cm);
            if (is_array($groups) && count($groups)) {
                list($select, $params) = $DB->get_in_or_equal(array_keys($groups));
            }
        } else {
            $argument = array_shift($arguments);
            switch ($argument->type) {

                case 'string':
                    list($select, $params) = $this->get_sql_like('name', $argument->value);
                    $select = "course = ? AND name $select";
                    array_unshift($params, $this->data->course);
                    $groupid = $DB->get_field_select('groups', 'id', $select, $params);
                    break;

                case 'integer':
                    $params = array('id' => $argument->value,
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
            if ($userids = $DB->get_records_select_menu('groups_members', "groupid $select", $params, 'userid', 'id, userid')) {
                $userids = array_unique($userids);
                $userids = array_flip($userids);
                if ($teachers = $this->valid_teacherids()) {
                    foreach ($teachers as $teacherid) {
                        unset($userids[$teacherid]);
                    }
                }
                if (count($userids)) {
                    $output = array_flip($userids);
                }
            }
        }
        return $output;
    }

    /**
     * compute_user
     * USER(format, user)
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_user($recordid, $arguments) {
        return $this->compute_users($recordid, $arguments, 'CURRENT_USER', false);
    }

    /**
     * compute_users
     * USERS(format, userids)
     *
     * @param array $arguments
     * @param mixed $default users
     * @param boolean $multiple TRUE if we should return multiple results; otherwise FALSE
     * @return string
     */
    protected function compute_users($recordid, $arguments, $default='CURRENT_USERS', $multiple=true) {
        global $DB;
        $output = '';

        $format = $this->compute($recordid, array_shift($arguments), 'DEFAULT_NAME_FORMAT');
        if ($format == '') {
            $format = 'default';
        }

        if ($userids = $this->compute($recordid, array_shift($arguments), $default)) {
            if ($multiple) {
                if (is_scalar($userids)) {
                    $userids = array($userids);
                }
                list($select, $params) = $DB->get_in_or_equal($userids);
                if ($users = $DB->get_records_select('user', "id $select", $params)) {
                    $output = array();
                    foreach ($users as $user) {
                        $output[$user->id] = $this->format_user_name($recordid, $format, $user);
                    }
                }
            } else {
                if (is_scalar($userids)) {
                    $userid = intval($userids);
                } else {
                    $userid = intval(reset($userids));
                }
                if ($user = $DB->get_record('user', array('id' => $userid))) {
                    $output = $this->format_user_name($recordid, $format, $user);
                }
            }
        }

        return $output;
    }

    /**
     * format_user_name
     *
     * @param string $format
     * @param object $user record from Moodle database
     * @return string $user formatted by $format string
     */
    protected function format_user_name($recordid, $format, $user) {

        if ($format == 'default') {
            $format = '';
        }

        // replace ENGLISH|JAPANESE NAME
        $format = preg_replace_callback(
            '/\b([A-Z]+)_NAME\b/i',
            function($matches) use ($user) {
                $name = $matches[0];
                switch ($matches[1]) {
                    case 'ENGLISH':
                    case 'INTERNATIONAL':
                    case 'LOWASCII':
                    case 'SINGLEBYTE':
                        $search = '/^[ -~]+$/';
                        if (preg_match($search, $user->firstname) && preg_match($search, $user->lastname)) {
                            return 'Firstname LASTNAME';
                        }
                        if (preg_match($search, $user->firstnamephonetic) && preg_match($search, $user->lastnamephonetic)) {
                            return 'Firstnamephonetic LASTNAMEPHONETIC';
                        }
                        $name = preg_replace('/[^ -~]+/', '', $user->alternatename);
                        $name = preg_replace('/[\(\)\{\}\[\]]+/', '', $name);
                        $name = preg_replace('/\s+/', ' ', trim($name));
                        return $name;

                    case 'CHINESE':
                    case 'JAPANESE':
                    case 'KOREAN':
                    case 'HIGHASCII':
                    case 'DOUBLEBYTE':
                        $search = '/^[^ -~]+$/';
                        if (preg_match($search, $user->firstname) && preg_match($search, $user->lastname)) {
                            return 'lastname firstname';
                        }
                        if (preg_match($search, $user->firstnamephonetic) && preg_match($search, $user->lastnamephonetic)) {
                            return 'lastnamephonetic firstnamephonetic';
                        }
                        $name = preg_replace('/[ -~]+/', '', $user->alternatename);
                        $name = preg_replace('/[\(\)\{\}\[\]]+/', '', $name);
                        $name = preg_replace('/\s+/', ' ', trim($name));
                        return $name;
                }
                return $name;
            },
            $format
        );

        // replace main user name fields
        $format = preg_replace_callback(
            '/\b(firstnamephonetic|lastnamephonetic|'.
                'firstname|middlename|lastname|'.
                'first|middle|last|'.
                'alternatename|alternate)\b/i',
            function($matches) use ($user) {
                $match = $matches[0];
                $name = strtolower($match);
                if (isset($user->$name)) {
                    if ($name == 'first' || $name == 'middle' || $name == 'last' || $name == 'alternate') {
                        $name .= 'name';
                    }
                    $name = $user->$name;
                    switch (true) {
                        case preg_match('/^[A-Z]+$/', $match):
                            $name = mb_convert_case($name, MB_CASE_LOWER);
                            break;
                        case preg_match('/^[a-z]+$/', $match):
                            $name = mb_convert_case($name, MB_CASE_LOWER);
                            break;
                        case preg_match('/^[A-Z][a-z]+$/', $match):
                            $name = mb_convert_case($name, MB_CASE_TITLE);
                            break;
                    }
                }
                return $name;
            },
            $format
        );

        // compute name functions (UPPERCASE, ROMANIZE, BRACKETS etc)
        $offset = 0;
        if ($arguments = $this->parse_arguments($recordid, $format, $offset)) {
            list($arguments, $offset) = $arguments;
            foreach ($arguments as $a => $argument) {
                $arguments[$a] = $this->compute($recordid, $argument);
            }
            $format = implode('', $arguments);
        }

        if ($format == '') {
            $format = fullname($user);
        }

        return $format;
    }

    /**
     * compute_brackets
     * BRACKETS(str)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_brackets($recordid, $arguments) {
        if ($str = $this->compute($recordid, array_shift($arguments))) {
            return "($str)";
        } else {
            return '';
        }
    }

    /**
     * compute_uppercase
     * UPPERCASE(str)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_uppercase($recordid, $arguments) {
        $text = $this->compute($recordid, array_shift($arguments));
        return mb_convert_case($text, MB_CASE_UPPER);
    }

    /**
     * compute_lowercase
     * LOWERCASE(str)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_lowercase($recordid, $arguments) {
        $text = $this->compute($recordid, array_shift($arguments));
        return mb_convert_case($text, MB_CASE_LOWER);
    }

    /**
     * compute_titlecase
     * TITLECASE(str)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_titlecase($recordid, $arguments) {
        $text = $this->compute($recordid, array_shift($arguments));
        return mb_convert_case($text, MB_CASE_TITLE);
    }

    /**
     * compute_romanize
     * ROMANIZE(str)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_romanize($recordid, $arguments) {
        $text = $this->compute($recordid, array_shift($arguments));

        if (preg_match(self::ROMAJI_STRING, $text)) {
            $text = self::romanize_romaji($text, $field);
        }

        if (preg_match(self::HIRAGANA_STRING, $text)) {
            $text = self::romanize_hiragana($text);
        }

        if (preg_match(self::KATAKANA_FULL_STRING, $text)) {
            $text = self::romanize_katakana_full($text);
        }

        if (preg_match(self::KATAKANA_HALF_STRING, $text)) {
            $text = self::romanize_katakana_half($text);
        }

        // what to do with these names:
        // ooizumi, ooie, ooba, oohama, tooru, iita (井板), fujii (藤井)
        // takaaki, maako, kousuke, koura, inoue, matsuura, yuuki
        // nanba, junpei, junichirou, shinya, shinnosuke, gonnokami, shinnou

        $text = strtr($text, array(
            'noue' => 'noue', 'kaaki' => 'kaaki',
            'aa' => 'ā', 'ii' => 'ī', 'uu' => 'ū',
            'ee' => 'ē', 'oo' => 'ō', 'ou' => 'ō'
        ));

        $text = strtr($text, array(
            'ooa' => "oh'a", 'ooi' => "oh'i", 'oou' => "oh'u",
            'ooe' => "oh'e", 'ooo' => "oh'o", 'too' => 'to',
            'oo'  => 'oh',   'ou'  => 'o',    'uu'  => 'u'
        ));

        return $text;
    }

    /**
     * compute_url
     * FILE_URL(field, records)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_url($recordid, $arguments) {
        return $this->compute_urls($recordid, $arguments, false);
    }

    /**
     * compute_urls
     * FILE_URLS(field, records)
     *
     * @param integer $recordid
     * @param array $arguments
     * @param boolean $multiple
     * @return integer
     */
    protected function compute_urls($recordid, $arguments, $multiple=true) {
        global $CFG, $DB;

        $field = $this->compute($recordid, array_shift($arguments));
        if (empty($field)) {
            return null;
        }

        $records = $this->compute($recordid, array_shift($arguments));
        if (empty($records)) {
            return null;
        }

        $dataid = $this->valid_dataid_from_recordids($records);
        if (empty($dataid)) {
            return null;
        }

        $fieldid = $this->valid_fieldid($dataid, $field);
        if (empty($fieldid)) {
            return null;
        }
        $fieldtype = $DB->get_field('data_fields', 'type', array('id' => $fieldid));

        list($select, $params) = $DB->get_in_or_equal($records);
        $select = "fieldid = ? AND recordid $select";
        array_unshift($params, $fieldid);

        if ($contents = $DB->get_records_select('data_content', $select, $params, 'id', 'id,recordid,content')) {

            if ($fieldtype == 'file' || $fieldtype == 'picture') {
                $cm = get_coursemodule_from_instance('data', $dataid);
                $context = context_module::instance($cm->id);
            } else {
                $cm = null;
                $context = null;
            }

            foreach ($contents as $id => $content) {
                if ($fieldtype == 'file' || $fieldtype == 'picture') {
                    $contents[$id] = "$CFG->wwwroot/pluginfile.php/$context->id/mod_data/content/$content->id/$content->content";
                } else {
                    $contents[$id] = $content->content;
                }
            }

            if ($multiple) {
                return $contents;
            } else {
                return reset($contents);
            }
        }

        // Oops, no record found :-(
        if ($multiple) {
            return array();
        } else {
            return null;
        }
    }

    /**
     * compute_LINK
     * LINK(url)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_link($recordid, $arguments) {
        if ($url = $this->compute($recordid, array_shift($arguments))) {
            $params = array('href' => $url);
            return html_writer::tag('a', $url, $params);
        }
        return null; // shouldn't happen !!
    }

    /**
     * compute_image
     * IMAGE(url)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_image($recordid, $arguments) {
        if ($url = $this->compute($recordid, array_shift($arguments))) {
            $params = array('src' => $url);
            return html_writer::empty_tag('img', $params);
        }
        return null; // shouldn't happen !!
    }

    /**
     * compute_audio
     * AUDIO(url)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_audio($recordid, $arguments) {
        if ($url = $this->compute($recordid, array_shift($arguments))) {
            $params = array('src' => $url,
                            'controls' => 'controls',
                            'preload' => 'metadata');
            return html_writer::tag('audio', '', $params);
        }
        return null; // shouldn't happen !!
    }

    /**
     * compute_video
     * VIDEO(url)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_video($recordid, $arguments) {
        if ($url = $this->compute($recordid, array_shift($arguments))) {
            $params = array('src' => $url,
                            'controls' => 'true',
                            'preload' => 'metadata',
                            'playsinline' => 'true');
            return html_writer::tag('video', '', $params);
        }
        return null; // shouldn't happen !!
    }

    /**
     * compute_list
     * LIST(items, listtype)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_list($recordid, $arguments) {
        if ($items = $this->compute($recordid, array_shift($arguments))) {
            $listtype = $this->compute($recordid, array_shift($arguments));
            return $this->format_list($items, $listtype);
        }
        return null; // shouldn't happen !!
    }

    /**
     * format_list
     *
     * @param array $items
     * @param string $listtype (UL, OL, DL)
     * @return integer
     */
    protected function format_list($items, $listtype, $params=null) {
        $list = '';
        if (is_array($items)) {

            $listtype = strtolower($listtype);

            // Use default list type, if necessary.
            $listtypes = array('ul', 'ol', 'dl');
            if (! in_array($listtype, $listtypes))  {
                $listtype = reset($listtypes);
            }

            // Set CSS class for the list.
            $name = 'class';
            $value = $this->field->name;
            if (is_array($params)) {
                if (empty($params[$name])) {
                    $params[$name] = $value;
                } else {
                    $params[$name] .= ' '.$value;
                }
            } else {
                $params = array($name => $value);
            }

            if ($listtype == 'dl') {
                $count = 0;
                foreach ($items as $id => $item) {
                    $count++;
                    if ($count % 2) {
                        $items[$id] = html_writer::tag('dt', $item);
                    } else {
                        $items[$id] = html_writer::tag('dd', $item);
                    }
                }
                if (count($items)) {
                    $list = html_writer::tag('dl', implode('', $items), $params);
                }
            } else {
                $items = array_filter($items);
                if (count($items)) {
                    $list = html_writer::alist($items, $params, $listtype);
                }
            }
        }
        return $list;
    }

    /**
     * compute_count_list
     * COUNT_LIST(list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_count_list($recordid, $arguments, $addtotal=true) {

        $items = $this->compute($recordid, array_shift($arguments));

        if (empty($items)) {
            return '';
        }

        if (is_scalar($items)) {
            $items = array($items);
        }

        $counts = array();
        foreach ($items as $item) {
            if (array_key_exists($item, $counts)) {
                $counts[$item]++;
            } else {
                $counts[$item] = 1;
            }
        }

        $total = array_sum($counts);

        // Sort by descending value, and maintain keys
        foreach ($counts as $item => $count) {
            if ($count == 1) {
                $strname = 'countvote';
            } else {
                $strname = 'countvotes';
            }
            $count = get_string($strname, 'datafield_report', $count);
            $count = html_writer::span($count, 'text-muted');
            $counts[$item] = html_writer::span($count.$item, 'countvotes');
        }

        arsort($counts);
        $counts = array_values($counts);

        if ($addtotal) {
            $total = $this->format_votes($total);
            $total = get_string('totalvotes', 'datafield_report', $total);
            $total = html_writer::span($total, 'border-top border-dark text-success');
            $counts[] = html_writer::span($total, 'totalvotes');
        }

        return $this->format_list($counts, 'ul', array('class' => 'list-unstyled'));
    }

    /**
     * compute_score_list
     * SCORE_LIST(field, records)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_score_list($recordid, $arguments, $addtotal=true) {

        list($items, $scores) = $this->get_items_scores(
            $recordid,
            $this->compute($recordid, array_shift($arguments)),
            $this->compute($recordid, array_shift($arguments))
        );

        if (empty($scores)) {
            return '';
        }

        $counts = array();
        foreach ($scores as $i) {
            if (array_key_exists($i, $counts)) {
                $counts[$i]++;
            } else {
                $counts[$i] = 1;
            }
        }

        // Sort by descending value, and maintain keys
        krsort($counts);

        foreach ($counts as $i => $count) {
            $a = (object)array(
                'totalpoints' => $this->format_points($count * $i),
                'numbervotes' => $this->format_votes($count),
                'numberpoints' => $this->format_points($i)
            );
            $count = get_string('scorelistitem', 'datafield_report', $a);
            $count = html_writer::span($count, 'text-muted');
            $counts[$i] = html_writer::span($count.$items[$i], 'scorelistitem');
        }

        if ($addtotal) {
            if ($count = count($scores)) {
                $total = array_sum($scores);
                $a = (object)array(
                    'totalpoints' => $this->format_points($total),
                    'countvotes' => $this->format_votes($count),
                    'averagepoints' => $this->format_points(round($total / $count, 1))
                );
                $total = get_string('scorelisttotal', 'datafield_report', $a);
                $total = html_writer::span($total, 'border-top border-dark text-success');
                $counts[] = html_writer::span($total, 'scorelisttotal');
            }
        }

        return $this->format_list($counts, 'ul', array('class' => 'list-unstyled'));
    }

    /**
     * compute_avg
     * AVG(list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_avg($recordid, $arguments) {
        $items = $this->compute($recordid, array_shift($arguments));
        if (empty($items)) {
            return 0;
        }
        return round(array_sum($items) / count($items), 1);
    }

    /**
     * compute_count
     * COUNT(list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_count($recordid, $arguments) {
        $items = $this->compute($recordid, array_shift($arguments));
        if (empty($items)) {
            return 0;
        }
        return count($items);
    }

    /**
     * compute_max
     * MAX(list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_max($recordid, $arguments) {
        $items = $this->compute($recordid, array_shift($arguments));
        if (empty($items)) {
            return 0;
        }
        return max($items);
    }

    /**
     * compute_min
     * MIN(list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_min($recordid, $arguments) {
        $items = $this->compute($recordid, array_shift($arguments));
        if (empty($items)) {
            return 0;
        }
        return min($items);
    }

    /**
     * compute_sort
     * SORT(list, sortdirection)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_sort($recordid, $arguments) {
        $items = $this->compute($recordid, array_shift($arguments));
        if (empty($items)) {
            return array();
        }
        $sort = $this->compute($recordid, array_shift($arguments));
        if ($sort && strtoupper($sort) == 'DESC') {
            rsort($items);
        } else {
            // default is ascending order ("ASC")
            sort($items);
        }
        return $items;
    }

    /**
     * compute_sum
     * SUM(list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_sum($recordid, $arguments) {
        $items = $this->compute($recordid, array_shift($arguments));
        if (empty($items)) {
            return 0;
        }
        return array_sum($items);
    }

    /**
     * compute_unique
     * UNIQUE(list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_unique($recordid, $arguments) {
        $items = $this->compute($recordid, array_shift($arguments));
        if (empty($items)) {
            return array();
        }
        return array_unique($items);
    }

    /**
     * compute_concat
     * CONCAT(list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return string
     */
    protected function compute_concat($recordid, $arguments) {
        $items = array();
        while ($item = $this->compute($recordid, array_shift($arguments))) {
            $items[] = $item;
        }
        if (empty($items)) {
            return '';
        }
        return trim(implode('', $items));
    }

    /**
     * compute_join
     * JOIN(shortstring, list)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return string
     */
    protected function compute_join($recordid, $arguments) {
        $str = $this->compute($recordid, array_shift($arguments));
        $items = $this->compute($recordid, array_shift($arguments));
        if (empty($items)) {
            return '';
        }
        $items = array_map('trim', $items);
        $items = array_filter($items);
        return implode($str, $items);
    }

    /**
     * compute_merge
     * MERGE(list1, list2, ...)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return array
     */
    protected function compute_merge($recordid, $arguments) {
        $merge = array();
        while ($items = $this->compute($recordid, array_shift($arguments))) {
            if (empty($items)) {
                continue;
            }
            if (is_scalar($items)) {
                $merge[] = $items;
            } else {
                $merge = array_merge($merge, $items);
            }
        }
        return $merge;
    }

    /**
     * compute_score
     * SCORE_LIST(scoretype, field, records)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_score($recordid, $arguments) {
        $scoretype = $this->compute($recordid, array_shift($arguments));
        return $this->score($scoretype, $recordid, $arguments);
    }

    /**
     * compute_score_avg
     * SCORE_AVG(field, records)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_score_avg($recordid, $arguments) {
        return $this->score('avg', $recordid, $arguments);
    }

    /**
     * compute_score_max
     * SCORE_MAX(field, records)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_score_max($recordid, $arguments) {
        return $this->score('max', $recordid, $arguments);
    }

    /**
     * compute_score_min
     * SCORE_MIN(field, records)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_score_min($recordid, $arguments) {
        return $this->score('min', $recordid, $arguments);
    }

    /**
     * compute_score_sum
     * SCORE_SUM(field, records)
     *
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function compute_score_sum($recordid, $arguments) {
        return $this->score('sum', $recordid, $arguments);
    }

    /**
     * compute_menu
     * MENU(list)
     *
     * @param array $arguments
     * @return string
     */
    protected function compute_menu($recordid, $arguments) {
        global $DB;
        $output = '';
        if ($items = $this->compute($recordid, array_shift($arguments))) {

            $fieldid = $this->field->id;
            $formfieldname = 'field_'.$fieldid;

            if ($recordid) {
                $params = array('fieldid' => $fieldid,
                                'recordid' => $recordid);
                $value = $DB->get_field('data_content', 'content', $params);
            } else {
                $value = '';
            }

            if (is_scalar($items)) {
                $items = explode(',', $items);
                $items = array_filter($items);
            }
            if (count($items) > 1) {
                // Add <label> for accessibility.
                $output .= $this->accessibility_label();

                // Add main <select> element.
                $choose = array('' => get_string('menuchoose', 'data'));
                $params = array('id' => $formfieldname,
                                'class' => 'mod-data-input custom-select');
                $output .= html_writer::select($items, $formfieldname, $value, $choose, $params);
            } else {
                // Only one value, so use hidden <input> element.
                $params = array('type' => 'hidden',
                                'name' => $formfieldname,
                                'value' => key($items));
                $output = html_writer::empty_tag('input', $params).reset($items);
            }
        }
        return $output;
    }

    /**
     * format the accessibility label for this field.
     */
    protected function accessibility_label() {
        global $OUTPUT;
        $label = html_writer::span($this->field->name, 'accesshide');
        if ($this->field->required) {
            $icon = $OUTPUT->pix_icon('req', get_string('requiredelement', 'form'));
            $label .= html_writer::div($icon, 'inline-req');
        }
        return html_writer::tag('label', $label, array('for' => 'field_'.$this->field->id));
    }

    /**
     * score
     *
     * @param string $scoretype ("sum", "avg", "min", "max")
     * @param integer $recordid
     * @param array $arguments
     * @return integer
     */
    protected function score($scoretype, $recordid, $arguments) {

        list($items, $scores) = $this->get_items_scores(
            $recordid,
            $this->compute($recordid, array_shift($arguments)),
            $this->compute($recordid, array_shift($arguments))
        );

        if (empty($scores)) {
            return '';
        }

        $count = count($scores);
        $countvalues = $this->format_values($count);

        switch ($scoretype) {
            case 'sum':
                $text = get_string('sumofvalues', 'datafield_report', $countvalues);
                $scores = array_sum($scores).html_writer::span($text, 'text-muted');
                break;

            case 'avg':
                $total = array_sum($scores);
                $text = get_string('averageofvalues', 'datafield_report', $countvalues);
                $scores = round($total/$count, 1).html_writer::span($text, 'text-muted');
                break;

            case 'min':
                $text = get_string('minimumofvalues', 'datafield_report', $countvalues);
                $scores = min($scores).html_writer::span($text, 'text-muted');
                break;

            case 'max':
                $text = get_string('maximumofvalues', 'datafield_report', $countvalues);
                $scores = max($scores).html_writer::span($text, 'text-muted');
                break;

            default:
                $scores = $scoretype.get_string('labelsep', 'langconfig').implode(', ', $scores);
        }
        return html_writer::span($scores, 'score');
    }

    /**
     * get_items_scores
     *
     * @param integer $recordid
     * @param mixed $field
     * @param mixed $arguments
     * @return array(score array, values array)
     */
    protected function get_items_scores($recordid, $field, $records) {
        global $DB;

        if (empty($field)) {
            return array(array(), array());
        }

        if (empty($records)) {
            return array(array(), array());
        }

        $dataid = $this->valid_dataid_from_recordids($records);
        $fieldid = $this->valid_fieldid($dataid, $field);

        list($select, $params) = $DB->get_in_or_equal($records);
        $select = "fieldid = ? AND recordid $select";
        array_unshift($params, $fieldid);

        if ($items = $DB->get_field('data_fields', 'param1', array('id' => $fieldid))) {
            $items = explode("\n", $items);
            $items = array_map('trim', $items);
            $items = array_filter($items);
            $items = array_unique($items);
            $items[] = '';
            $items = array_reverse($items);
        } else {
            $items = array();
        }

        $scores = array();
        if ($contents = $DB->get_records_select_menu('data_content', $select, $params, 'id', 'id,content')) {
            $contents = array_map('trim', $contents);
            $contents = array_filter($contents);
            foreach ($contents as $content) {
                if (in_array($content, $items)) {
                    $scores[] = array_search($content, $items);
                }
            }
        }

        return array($items, $scores);
    }

    /**
     * format_points
     *
     * @param integer number of $points
     * @return string
     */
    protected function format_points($points) {
        return $this->format_plural('point', $points);
    }

    /**
     * format_values
     *
     * @param integer number of $values
     * @return string
     */
    protected function format_values($values) {
        return $this->format_plural('value', $values);
    }

    /**
     * format_votes
     *
     * @param integer number of $votes
     * @return string
     */
    protected function format_votes($votes) {
        return $this->format_plural('vote', $votes);
    }

    /**
     * format_plural
     *
     * @param integer number of $votes
     * @return string
     */
    protected function format_plural($type, $number) {
        if ($number == 1) {
            return get_string("one{$type}", 'datafield_report');
        } else {
            return get_string("many{$type}s", 'datafield_report', $number);
        }
    }

    /**
     * valid_dataid
     *
     * @param mixed $database
     * @param integer $dataid
     * @return string
     */
    protected function valid_dataid($database='') {
        global $DB;
        static $dataids = array();

        if (! array_key_exists($database, $dataids)) {

            $cm = null;
            switch (true) {

                case empty($database):
                    $cm = $this->cm;
                    break;

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
     * @param mixed $database
     * @param mixed $field
     * @return mixed fieldid OR null
     */
    protected function valid_fieldid($database, $field) {
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
     * @param mixed $database
     * @param mixed $field
     * @param mixed $value
     * @param boolean $multiple
     * @return mixed fieldid OR null
     */
    protected function valid_recordids($database, $field, $value, $multiple) {
        global $DB;

        $fieldid = $this->valid_fieldid($database, $field);

        list($select, $params) = $DB->get_in_or_equal($fieldid);
        $select = "fieldid $select AND content = ?";
        array_push($params, $value);

        if ($records = $DB->get_records_select_menu('data_content', $select, $params, 'id', 'id,recordid')) {
            if ($multiple) {
                return array_values($records);
            } else {
                return reset($records);
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
     * valid_user_recordids
     *
     * @param mixed $field
     * @param mixed $database
     * @param boolean $multiple
     * @return mixed fieldid OR null
     */
    protected function valid_user_recordids($database, $users, $multiple) {
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

    /**
     * Return all ids of all users who the current user has access to within the specified database.
     * In addition, if $users is specified, it will be used to filter the list of returned userids.
     */
    protected function valid_userids($database='', $userids=null) {
        global $DB, $USER;
        static $staticuserids = array();

        $dataid = $this->valid_dataid($database);

        if (! array_key_exists($dataid, $staticuserids)) {
            $staticuserids[$dataid] = array();

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
                    // $groupmode == SEPARATEGROUPS
                    $accessallusers = has_capability('moodle/site:accessallgroups', $context);
                }
                if ($accessallusers) {
                    $staticuserids[$dataid] = get_enrolled_users($context);
                    $staticuserids[$dataid] = array_keys($staticuserids[$dataid]);
                } else {
                    if ($groups = groups_get_activity_allowed_groups($this->cm)) {
                        list($select, $params) = $DB->get_in_or_equal(array_keys($groups));
                        $staticuserids[$dataid] = $DB->get_records_select_menu('groups_members', "groupid $select", $params, 'id', 'id,userid');
                        if ($staticuserids[$dataid]) {
                            $staticuserids[$dataid] = array_unique($staticuserids[$dataid]);
                            $staticuserids[$dataid] = array_values($staticuserids[$dataid]);
                        } else {
                            $staticuserids[$dataid] = array();
                        }
                    }
                }
                sort($staticuserids[$dataid]);
            }
        }

        if (empty($userids)) {
            return $staticuserids[$dataid];
        } else {
            return array_intersect($staticuserids[$dataid], $userids);
        }
    }

    /**
     * get list of teachers (including non-editing teachers) in this course
     */
    protected function valid_studentids() {
        if ($this->studentids === null) {
            if ($this->studentids = get_users_by_capability($this->context, 'mod/data:viewentry', 'u.id,u.username')) {
                $this->studentids = array_keys($this->studentids);
                $this->studentids = array_diff($this->studentids, $this->valid_teacherids());
            } else {
                $this->studentids = array();
            }
        }
        return $this->studentids;
    }

    /**
     * get list of teachers (including non-editing teachers) in this course
     */
    protected function valid_teacherids() {
        if ($this->teacherids === null) {
            if ($this->teacherids = get_users_by_capability($this->context, 'mod/data:manageentries', 'u.id,u.username')) {
                $this->teacherids = array_keys($this->teacherids);
            } else {
                $this->teacherids = array();
            }
        }
        return $this->teacherids;
    }

    protected function valid_groupids($groups, $course, $multiple) {
        global $DB;
        static $staticgroupids = array();

        if (! array_key_exists($groups, $staticgroupids)) {
            $activitygroupids = groups_get_activity_allowed_groups($this->cm);
            $activitygroupids = array_keys($activitygroupids);
            if ($groups) {
                $staticgroupids[$groups] = array();
                if (preg_match('/^[0-9]+$/', $groups)) {
                    $params = array('id' => $groups, 'course' => $this->data->course);
                    if ($records = $DB->get_records('groups', $params, 'id', 'id,course')) {
                        $staticgroupids[$groups] = array_keys($records);
                    }
                } else {
                    list($select, $params) = $this->get_sql_like('name', $groups);
                    $select = "course = ? AND $select";
                    array_unshift($params, $this->data->course);
                    if ($records = $DB->get_records_select('groups', $select, $params, 'id', 'id,course')) {
                        $staticgroupids[$groups] = array_keys($records);
                    }
                }
                $staticgroupids[$groups] = array_intersect($staticgroupids[$groups], $activitygroupids);
            } else {
                $staticgroupids[$groups] = $activitygroupids;
            }
        }

        if ($multiple) {
            return $staticgroupids[$groups];
        } else {
            return reset($staticgroupids[$groups]);
        }
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
        $text = str_replace('*', '%', $text);

        if (strpos($text, '%') === false) {
            $select = "$name = ?";
        } else {
            $text = preg_replace('/%%+/', '%', $text);
            $select = $DB->sql_like($name, '?');
        }
        return array($select, array($text));
    }

    /**
     * romanize_romaji
     *
     * @return string
     */
    static public function romanize_romaji($name, $field='firstname') {

        // convert to lowercase
        $name= self::textlib('strtolower', $name);

        // fix "si", "ti", "tu", "sy(a|u|o)", "jy(a|u|o)" and "nanba"
        $name = strtr($name, array(
            'si' => 'shi', 'ti' => 'chi', 'tu' => 'tsu',
            'sy' => 'sh',  'jy' =>'j',    'nb' => 'mb'
        ));

        // fix "hu" (but not "chu" or "shu") e.g. hujimura
        $name = preg_replace('/(?<![cs])hu/', 'fu', $name);

        if (is_numeric(strpos($field, 'firstname'))) {
            // kiyou(hei)
            // shiyou(go|hei|ta|tarou)
            // shiyun(suke|ya), shiyuu(ji|ta|tarou|ya)
            // riyou(ga|ki|suke|ta|tarou|ya)
            // riyuu(ichi|ki|ta|ma|saku|sei|shi|zou)
            $replace = array(
                'kiyou'  => 'kyou',
                'shiyou' => 'shou', 'jiyou' => 'jou',
                'shiyuu' => 'shuu', 'jiyuu' => 'juu',
                'shiyun' => 'shun', 'jiyun' => 'jun',
                'riyou'  => 'ryou', 'riyuu' => 'ryuu'
            );
        } else {
            // gasshiyou (GASSHŌ)
            // miyoujin (MYŌJIN)
            // mukaijiyou (MUKAIJŌ)
            // chiya(da|ta)ani (not UCHIYAMA or TSUCHIYA)
            $replace = array(
                'shiyou'    => 'shou',
                'jiyou'     => 'jou',
                'miyou'     => 'myou',
                'chiyatani' => 'chatani',
                'chiyadani' => 'chadani'
            );
        }

        return self::romanize($name, '', $replace);
    }

    /**
     * romanize_hiragana
     *
     * @param string $name
     * @return string $name
     */
    static public function romanize_hiragana($name) {
        return self::romanize($name, 'っ', array(
            // space
            '　' => ' ',

            // two-char (double-byte hiragana)
            'きゃ' => 'kya', 'ぎゃ' => 'gya', 'しゃ' => 'sha', 'じゃ' => 'ja',
            'ちゃ' => 'cha', 'にゃ' => 'nya', 'ひゃ' => 'hya', 'りゃ' => 'rya',

            'きゅ' => 'kyu', 'ぎゅ' => 'gyu', 'しゅ' => 'shu', 'じゅ' => 'ju',
            'ちゅ' => 'chu', 'にゅ' => 'nyu', 'ひゅ' => 'hyu', 'りゅ' => 'ryu',

            'きょ' => 'kyo', 'ぎょ' => 'gyo', 'しょ' => 'sho', 'じょ' => 'jo',
            'ちょ' => 'cho', 'にょ' => 'nyo', 'ひょ' => 'hyo', 'りょ' => 'ryo',

            'んあ' => "n'a", 'んい' => "n'i", 'んう' => "n'u", 'んえ' => "n'e", 'んお' => "n'o",
            'んや' => "n'ya", 'んゆ' => "n'yu", 'んよ' => "n'yo",

            // one-char (double-byte hiragana)
            'あ' => 'a', 'い' => 'i', 'う' => 'u', 'え' => 'e', 'お' => 'o',
            'か' => 'ka', 'き' => 'ki', 'く' => 'ku', 'け' => 'ke', 'こ' => 'ko',
            'が' => 'ga', 'ぎ' => 'gi', 'ぐ' => 'gu', 'げ' => 'ge', 'ご' => 'go',
            'さ' => 'sa', 'し' => 'shi', 'す' => 'su', 'せ' => 'se', 'そ' => 'so',
            'ざ' => 'za', 'じ' => 'ji', 'ず' => 'zu', 'ぜ' => 'ze', 'ぞ' => 'zo',
            'た' => 'ta', 'ち' => 'chi', 'つ' => 'tsu', 'て' => 'te', 'と' => 'to',
            'だ' => 'da', 'ぢ' => 'ji', 'づ' => 'zu', 'で' => 'de', 'ど' => 'do',
            'な' => 'na', 'に' => 'ni', 'ぬ' => 'nu', 'ね' => 'ne', 'の' => 'no',
            'は' => 'ha', 'ひ' => 'hi', 'ふ' => 'fu', 'へ' => 'he', 'ほ' => 'ho',
            'ば' => 'ba', 'び' => 'bi', 'ぶ' => 'bu', 'べ' => 'be', 'ぼ' => 'bo',
            'ぱ' => 'pa', 'ぴ' => 'pi', 'ぷ' => 'pu', 'ぺ' => 'pe', 'ぽ' => 'po',
            'ま' => 'ma', 'み' => 'mi', 'む' => 'mu', 'め' => 'me', 'も' => 'mo',
            'や' => 'ya', 'ゆ' => 'yu', 'よ' => 'yo',
            'ら' => 'ra', 'り' => 'ri', 'る' => 'ru', 'れ' => 're', 'ろ' => 'ro',
            'わ' => 'wa', 'を' => 'o', 'ん' => 'n'
        ));
    }

    /**
     * romanize_katakana_full
     *
     * @param string $name
     * @return string $name
     */
    static public function romanize_katakana_full($name) {
        return self::romanize($name, 'ッ', array(
            // space
            '　' => ' ',

            // two-char (full-width katakana)
            'キャ' => 'kya', 'ギャ' => 'gya', 'シャ' => 'sha', 'ジャ' => 'ja',
            'チャ' => 'cha', 'ニャ' => 'nya', 'ヒャ' => 'hya', 'リャ' => 'rya',

            'キュ' => 'kyu', 'ギュ' => 'gyu', 'シュ' => 'shu', 'ジュ' => 'ju',
            'チュ' => 'chu', 'ニュ' => 'nyu', 'ヒュ' => 'hyu', 'リュ' => 'ryu',

            'キョ' => 'kyo', 'ギョ' => 'gyo', 'ショ' => 'sho', 'ジョ' => 'jo',
            'チョ' => 'cho', 'ニョ' => 'nyo', 'ヒョ' => 'hyo', 'リョ' => 'ryo',

            'ンア' => "n'a", 'ンイ' => "n'i", 'ンウ' => "n'u", 'ンエ' => "n'e", 'ンオ' => "n'o",
            'ンヤ' => "n'ya", 'ンユ' => "n'yu", 'ンヨ' => "n'yo",

            // one-char (full-width katakana)
            'ア' => 'a', 'イ' => 'i', 'ウ' => 'u', 'エ' => 'e', 'オ' => 'o',
            'カ' => 'ka', 'キ' => 'ki', 'ク' => 'ku', 'ケ' => 'ke', 'コ' => 'ko',
            'ガ' => 'ga', 'ギ' => 'gi', 'グ' => 'gu', 'ゲ' => 'ge', 'ゴ' => 'go',
            'サ' => 'sa', 'シ' => 'shi', 'ス' => 'su', 'セ' => 'se', 'ソ' => 'so',
            'ザ' => 'za', 'ジ' => 'ji', 'ズ' => 'zu', 'ゼ' => 'ze', 'ゾ' => 'zo',
            'タ' => 'ta', 'チ' => 'chi', 'ツ' => 'tsu', 'テ' => 'te', 'ト' => 'to',
            'ダ' => 'da', 'ヂ' => 'ji', 'ヅ' => 'zu', 'デ' => 'de', 'ド' => 'do',
            'ナ' => 'na', 'ニ' => 'ni', 'ヌ' => 'nu', 'ネ' => 'ne', 'ノ' => 'no',
            'ハ' => 'ha', 'ヒ' => 'hi', 'フ' => 'fu', 'ヘ' => 'he', 'ホ' => 'ho',
            'バ' => 'ba', 'ビ' => 'bi', 'ブ' => 'bu', 'ベ' => 'be', 'ボ' => 'bo',
            'パ' => 'pa', 'ピ' => 'pi', 'プ' => 'pu', 'ペ' => 'pe', 'ポ' => 'po',
            'マ' => 'ma', 'ミ' => 'mi', 'ム' => 'mu', 'メ' => 'me', 'モ' => 'mo',
            'ヤ' => 'ya', 'ユ' => 'yu', 'ヨ' => 'yo',
            'ラ' => 'ra', 'リ' => 'ri', 'ル' => 'ru', 'レ' => 're', 'ロ' => 'ro',
            'ワ' => 'wa', 'ヲ' => 'o', 'ン' => 'n'
        ));
    }

    /**
     * romanize_katakana_full
     *
     * @param string $name
     * @return string $name
     */
    static public function romanize_katakana_half($name) {
        return self::romanize($name, 'ｯ', array(
            // space
            '　' => ' ',

            // two-char (half-width katakana)
            'ｷｬ' => 'kya', 'ｷﾞｬ' => 'gya', 'ｼｬ' => 'sha', 'ｼﾞｬ' => 'ja',
            'ﾁｬ' => 'cha', 'ﾆｬ' => 'nya', 'ﾋｬ' => 'hya', 'ﾘｬ' => 'rya',

            'ｷｭ' => 'kyu', 'ｷﾞｭ' => 'gyu', 'ｼｭ' => 'shu', 'ｼﾞｭ' => 'ju',
            'ﾁｭ' => 'chu', 'ﾆｭ' => 'nyu', 'ﾋｭ' => 'hyu', 'ﾘｭ' => 'ryu',

            'ｷｮ' => 'kyo', 'ｷﾞｮ' => 'gyo', 'ｼｮ' => 'sho', 'ｼﾞｮ' => 'jo',
            'ﾁｮ' => 'cho', 'ﾆｮ' => 'nyo', 'ﾋｮ' => 'hyo', 'ﾘｮ' => 'ryo',

            'ｶﾞ' => 'ga', 'ｷﾞ' => 'gi', 'ｸﾞ' => 'gu', 'ｹﾞ' => 'ge', 'ｺﾞ' => 'go',
            'ｻﾞ' => 'za', 'ｼﾞ' => 'ji', 'ｽﾞ' => 'zu', 'ｾﾞ' => 'ze', 'ｿﾞ' => 'zo',
            'ﾀﾞ' => 'da', 'ﾁﾞ' => 'ji', 'ﾂﾞ' => 'zu', 'ﾃﾞ' => 'de', 'ﾄﾞ' => 'do',
            'ﾊﾞ' => 'ba', 'ﾋﾞ' => 'bi', 'ﾌﾞ' => 'bu', 'ﾍﾞ' => 'be', 'ﾎﾞ' => 'bo',
            'ﾊﾟ' => 'pa', 'ﾋﾟ' => 'pi', 'ﾌﾟ' => 'pu', 'ﾍﾟ' => 'pe', 'ﾎﾟ' => 'po',

            'ﾝｱ' => "n'a", 'ﾝｲ' => "n'i", 'ﾝｳ' => "n'u", 'ﾝｴ' => "n'e", 'ﾝｵ' => "n'o",
            'ﾝﾔ' => "n'ya", 'ﾝﾕ' => "n'yu", 'ﾝﾖ' => "n'yo",

            // one-char (half-width katakana)
            'ｱ' => 'a', 'ｲ' => 'i', 'ｳ' => 'u', 'ｴ' => 'e', 'ｵ' => 'o',
            'ｶ' => 'ka', 'ｷ' => 'ki', 'ｸ' => 'ku', 'ｹ' => 'ke', 'ｺ' => 'ko',
            'ｻ' => 'sa', 'ｼ' => 'shi', 'ｽ' => 'su', 'ｾ' => 'se', 'ｿ' => 'so',
            'ﾀ' => 'ta', 'ﾁ' => 'chi', 'ﾂ' => 'tsu', 'ﾃ' => 'te', 'ﾄ' => 'to',
            'ﾅ' => 'na', 'ﾆ' => 'ni', 'ﾇ' => 'nu', 'ﾈ' => 'ne', 'ﾉ' => 'no',
            'ﾊ' => 'ha', 'ﾋ' => 'hi', 'ﾌ' => 'fu', 'ﾍ' => 'he', 'ﾎ' => 'ho',
            'ﾏ' => 'ma', 'ﾐ' => 'mi', 'ﾑ' => 'mu', 'ﾒ' => 'me', 'ﾓ' => 'mo',
            'ﾔ' => 'ya', 'ﾕ' => 'yu', 'ﾖ' => 'yo',
            'ﾗ' => 'ra', 'ﾘ' => 'ri', 'ﾙ' => 'ru', 'ﾚ' => 're', 'ﾛ' => 'ro',
            'ﾜ' => 'wa', 'ｦ' => 'o', 'ﾝ' => 'n'
        ));
    }

    /**
     * romanize
     */
    static public function romanize($name, $tsu='', $replace=null) {
        if ($replace) {
            $name = strtr($name, $replace);
        }
        if ($tsu) {
            $name = preg_replace('/'.$tsu.'(.)/u', '$1$1', $name);
        }
        return str_replace('nb', 'mb', $name);
    }

    /**
     * get list of action times
     */
    static public function get_restore_types() {
        return array(
            'default' => get_string('default', 'grades'),
            'ignore' => get_string('ignore', 'grades'),
            'username' => get_string('username', 'moodle'),
            'fullnameuser' => get_string('fullnameuser', 'moodle'), // User full name
            'groupname' => get_string('groupname', 'group'),
            'activityname' => get_string('basicltiname', 'lti'), // Moodle >= 2.2
            'coursename' => get_string('coursename', 'grades'),
        );
    }

}