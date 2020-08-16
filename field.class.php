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
    // MY_GROUP_IDS("group name"): SELECT id FROM {groups} WHERE name = "My group name" AND courseid = MY_COURSEID;

    // MY_GROUP_USERIDS(): SELECT userid FROM mdl_groups_members WHERE groupid IN MY_GROUP_IDS()
    // MY_GROUP_USERIDS("group name"): SELECT userid FROM mdl_groups_members WHERE groupid IN MY_GROUP_IDS("group name")


    /**
     * displays the settings for this field on the "Fields" page
     *
     * @return void, but output is echo'd to browser
     */
    function display_edit_field() {
        data_field_admin::check_lang_strings($this);
        parent::display_edit_field();
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
}
