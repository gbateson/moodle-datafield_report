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

class data_field_report extends data_field_base {

    /**
     * the main type of this database field
     * as required by the database module
     */
    var $type = 'report';

    ///////////////////////////////////////////
    // custom properties
    ///////////////////////////////////////////

    /**
     * the subtype of this database field
     * e.g. radiobutton
     */
    var $subtype = '';

    /**
     * the PHP class of the subtype of this database field
     * e.g. database_field_radiobutton
     */
    var $subclass = '';

    /**
     * the full path to the folder of the subfield of this field
     * e.g. /path/to/moodle/mod/data/field/radiobutton
     */
    var $subfolder = '';

    /**
     * an object containing the subfield for this field
     */
    var $subfield = null;

    /**
     * the $field property that contains the subtype of this field
     */
    var $subparam = 'param10';

    /**
     * the $field property that contains the accessibility setting of this field
     */
    var $accessparam = 'param9';

    /**
     * the $field property that contains disabledIf information for this field
     */
    var $disabledifparam = 'param8';

    /**
     * TRUE if the current user can view this field; otherwise FALSE
     */
    var $is_viewable = false;

    /**
     * TRUE if the current user can edit this field; otherwise FALSE
     */
    var $is_editable = false;

    /**
     * TRUE if the field is a special field that cannot be viewed or altered by anyone
     */
    var $is_special_field = false;

    /**
     * TRUE if we should force this record to be unapproved; otherwise FALSE
     *
     * This flag is set to TRUE automatically under the following conditions:
     * (1) field name is "unapprove"
     * (2) new record is being added
     * (3) user is a teacher or report
     *
     * In the add single template, you should include the field, [[unapprove]].
     * It will not be displayed or editable, but it will be added as a hidden field,
     * and will be processed below in the "update_content()" method of this PHP class
     *
     * The subtype of the "unapprove" field should be "number" or "text"
     * because setting  the subtype to "radio" or "checkbox"
     * will cause an error when adding a new entry
     */
     var $unapprove = false;

    ///////////////////////////////////////////
    // fields that are NOT IMPLEMENTED ... yet
    ///////////////////////////////////////////

    /**
     * the $field property that contains line of display text
     * for select, radio, and checkbox subfields
     * e.g. value, multilingual display text
     */
    var $displaytextparam = 'param7';

    /**
     * the $field property that contains the sort order for this field
     */
    var $sortorderparam = 'param6';

    // implement validation on values
    //  - PARAM_xxx
    // can we filter_string output to view pages?
    // can we add field description to export?

    ///////////////////////////////////////////
    // custom constants
    ///////////////////////////////////////////

    const ACCESS_NONE =  0; // hidden
    const ACCESS_VIEW =  1; // can view
    const ACCESS_EDIT =  2; // can edit

    ///////////////////////////////////////////
    // standard methods
    ///////////////////////////////////////////

    /**
     * constructor
     *
     * @param object $field record from "data_fields" table
     * @param object $data record from "data" table
     * @param object $cm record from "course_modules" table
     */
    function __construct($field=0, $data=0, $cm=0) {
        global $CFG, $DB, $datarecord;

        // set up this field in the normal way
        parent::__construct($field, $data, $cm);

        $subparam         = $this->subparam;
        $accessparam      = $this->accessparam;
        $disabledifparam  = $this->disabledifparam;
        $displaytextparam = $this->displaytextparam;
        $sortorderparam   = $this->sortorderparam;

        $this->is_special_field = ($this->field->name=='unapprove' ||
                                   $this->field->name=='fixdisabledfields');

        // set view and edit permissions for this user
        if ($this->field && $this->is_special_field) {

            // special fields are not viewable or editable by anyone
            $this->is_editable = false;
            $this->is_viewable = false;

            // field-specific processing for new fields
            if (optional_param('rid', 0, PARAM_INT)==0) {
                switch ($this->field->name) {

                    case 'fixdisabledfields':
                        // prevent "missing property" error in data/lib.php
                        // caused by disabled fields in form
                        if (isset($datarecord) && is_object($datarecord)) {
                            $select = 'dataid = ?';
                            $params = array('field_', $this->data->id);
                            $name = $DB->sql_concat('?', 'id').' AS formfieldname';
                            if ($names = $DB->get_records_select_menu('data_fields', $select, $params, 'id', "id, $name")) {
                                foreach ($names as $name) {
                                    if (! property_exists($datarecord, $name)) {
                                        $datarecord->$name = null;
                                    }
                                }
                            }
                        }
                        break;

                    case 'unapprove':
                        // By default records added by teachers and reports
                        // have their "approved" flag set to "1".
                        // We want to detect this situation, so that we can
                        // override it later, in the update_content() method
                        $this->unapprove = has_capability('mod/data:approve', $this->context);
                        break;
                }
            }
        } else if (has_capability('mod/data:managetemplates', $this->context)) {
            $this->is_editable = true;
            $this->is_viewable = true;
        } else if (isset($field->$accessparam)) {
            $this->is_viewable = ($field->$accessparam >= self::ACCESS_VIEW);
            $this->is_editable = ($field->$accessparam >= self::ACCESS_EDIT);
        }

        // fetch the subfield if there is one
        if (isset($field->$subparam)) {
            $subtype = $field->$subparam;
            $subclass = 'data_field_'.$subtype;
            $subfolder = $CFG->dirroot.'/mod/data/field/'.$subtype;
            $filepath = $subfolder.'/field.class.php';
            if (file_exists($filepath)) {
                require_once($filepath);
                $this->subtype = $subtype;
                $this->subclass = $subclass;
                $this->subfolder = $subfolder;
                $this->subfield = new $subclass($field, $data, $cm);
            }
        }
    }

    function define_default_field() {
        parent::define_default_field();

        $param = $this->subparam;
        $this->field->$param = '';

        $param = $this->accessparam;
        $this->field->$param = self::ACCESS_VIEW;

        $param = $this->disabledifparam;
        $this->field->$param = '';

        if ($this->subfield) {
            $this->subfield->define_default_field();
        }
        return true;
    }

    function define_field($data) {
        parent::define_field($data);

        $param = $this->subparam;
        if (isset($data->$param)) {
            $this->field->$param = trim($data->$param);
        }

        $param = $this->accessparam;
        if (isset($data->$param)) {
            $this->field->$param = intval($data->$param);
        }

        $param = $this->disabledifparam;
        if (isset($data->$param)) {
            $this->field->$param = trim($data->$param);
        }

        if ($this->subfield) {
            $this->subfield->define_field($data);
        }
        return true;
    }

    /**
     * generate HTML to display icon for this field type on the "Fields" page
     */
    function image() {
        if ($this->subfield) {
            return $this->subfield->image();
        } else {
            return parent::image();
        }
    }

    /**
     * the name of this field type on the page for editing this field's settings
     */
    function name() {
        $name = get_string('report');
        if (isset($this->subfield)) {
            $subname = $this->subfield->name();
            $subname = core_text::strtolower($subname);
            $name .= " ($subname)";
        }
        return $name;
    }

    /**
     * displays the settings for this report field on the "Fields" page
     *
     * @return void, but output is echo'd to browser
     */
    function display_edit_field() {
        global $CFG, $OUTPUT;
        if (empty($this->field->id)) {
            $strman = get_string_manager();
            if (! $strman->string_exists($this->type, 'data')) {
                $msg = (object)array(
                    'langfile' => $CFG->dirroot.'/mod/data/lang/en/data.php',
                    'readfile' => $CFG->dirroot.'/mod/data/field/report/README.txt',
                );
                $msg = get_string('fixlangpack', 'datafield_'.$this->type, $msg);
                $msg = format_text($msg, FORMAT_MARKDOWN);
                $msg = html_writer::tag('div', $msg, array('class' => 'alert', 'style' => 'width: 100%; max-width: 640px;'));
                echo $msg;
            }
        }
        parent::display_edit_field();
    }

    /**
     * add a new report field from the "Fields" page
     */
    function insert_field() {
        if ($this->subfield) {
            return $this->subfield->insert_field();
        } else {
            return parent::insert_field();
        }
    }

    /**
     * update settings for this report field sent from the "Fields" page
     *
     * @return void, but output is echo'd to browser
     */
    function update_field() {
        parent::update_field();
        if ($this->subfield) {
            $this->subfield->update_field();
        }
        return true;
    }

    /**
     * delete an report field from the "Fields" page
     */
    function delete_field() {
        if ($this->subfield) {
            $this->subfield->delete_field();
        } else {
            parent::delete_field();
        }
        return true;
    }

    /**
     * delete user generated content associated with an report field
     * when the report field is deleted from the "Fields" page
     */
    function delete_content($recordid=0) {
        if ($this->subfield) {
            return $this->subfield->delete_content($recordid);
        } else {
            return parent::delete_content($recordid);
        }
    }

    /**
     * display a form element for this field on the "Add entry" page
     *
     * @return HTML to send to browser
     */
    function display_add_field($recordid=0, $formdata=NULL) {
        $output = '';
        if ($this->is_special_field) {
            return $this->format_edit_hiddenfield('field_'.$this->field->id, 1);
        }
        if ($this->is_editable) {
            $this->js_setup_fields(); // does not add anything to $output
            if ($this->subfield) {
                $output .= $this->subfield->display_add_field($recordid, $formdata);
            } else {
                $output .= parent::display_add_field($recordid, $formdata);
            }
        } else {
            $output.= $this->display_browse_field($recordid, '');
        }
        return $output;
    }

    /**
     * update content for this field sent from the "Add entry" page
     *
     * @return boolean: TRUE if content was sccessfully updated; otherwise FALSE
     */
    function update_content($recordid, $value, $name='') {
        global $DB;
        if ($this->subfield) {
            if ($this->unapprove) {
                // override the automatic approval of records created by teachers and reports
                return $DB->set_field('data_records', 'approved', 0, array('id' => $recordid));
            }
            return $this->subfield->update_content($recordid, $value, $name);
        } else {
            return parent::update_content($recordid, $value, $name);
        }
        return false;
    }

    /**
     * display this field on the "View list" or "View single" page
     */
    function display_browse_field($recordid, $template) {
        if ($this->is_viewable) {
            if ($this->subfield) {
                return $this->subfield->display_browse_field($recordid, $template);
            } else {
                return parent::display_browse_field($recordid, $template);
            }
        }
        return ''; // field is not viewable
    }

    /**
     * display a form element for this field on the "Search" page
     *
     * @return HTML to send to browser
     */
    function display_search_field() {
        if ($this->is_viewable) {
            if ($this->subfield) {
                return $this->subfield->display_search_field();
            } else {
                return parent::display_search_field();
            }
        }
    }

    /**
     * add extra HTML before the form on the "Add entry" page
     * Note: this method doesn't seem to be used anywhere !!
     */
    function print_before_form() {
        if ($this->is_viewable) {
            if ($this->subfield) {
                return $this->subfield->print_before_form();
            } else {
                return parent::print_before_form();
            }
        }
    }

    /**
     * add extra HTML after the form on the "Add entry" page
     */
    function print_after_form() {
        if ($this->is_viewable) {
            if ($this->subfield) {
                $this->subfield->print_after_form();
            } else {
                parent::print_after_form();
            }
        }
    }

    /**
     * parse search field from "Search" page
     */
    function parse_search_field() {
        if ($this->is_viewable) {
            if ($this->subfield) {
                return $this->subfield->parse_search_field();
            } else {
                return parent::parse_search_field();
            }
        } else {
            return '';
        }
    }

    function notemptyfield($value, $name) {
        if ($this->subfield) {
            return $this->subfield->notemptyfield($value, $name);
        } else {
            return parent::notemptyfield($value, $name);
        }
    }

    function get_sort_field() {
        if ($this->subfield) {
            return $this->subfield->get_sort_field();
        } else {
            return parent::get_sort_field();
        }
    }

    function get_sort_sql($fieldname) {
        if ($this->subfield) {
            return $this->subfield->get_sort_sql($fieldname);
        } else {
            return parent::get_sort_sql($fieldname);
        }
    }

    function text_export_supported() {
        if ($this->subfield) {
            return $this->subfield->text_export_supported();
        } else {
            return parent::text_export_supported();
        }
    }

    function export_text_value($record) {
        if ($this->subfield) {
            return $this->subfield->export_text_value($record);
        } else {
            return parent::export_text_value($record);
        }
    }

    function file_ok($relativepath) {
        if ($this->subfield) {
            return $this->subfield->file_ok($relativepath);
        } else {
            return parent::file_ok($relativepath);
        }
    }

    /**
     * generate sql required for search page
     * Note: this function is missing from the parent class :-(
     */
    function generate_sql($tablealias, $value) {
        if ($this->is_viewable && $this->subfield) {
            return $this->subfield->generate_sql($tablealias, $value);
        } else {
            return '';
        }
    }

    ///////////////////////////////////////////
    // custom methods
    ///////////////////////////////////////////

    /**
     * Allow access to subfield values
     * even though the subfield may not be viewable.
     * This allows the value to be used in IF-THEN-ELSE
     * conditions within "template" fields.
     */
    function get_condition_value($recordid, $template) {
        $is_viewable = $this->is_viewable;
        $this->is_viewable = true;
        $value = $this->display_browse_field($recordid, $template);
        $this->is_viewable = $is_viewable;
        return $value;
    }

    /**
     * get options for field accessibility (for display in mod.html)
     */
    public function get_access_types() {
        return array(self::ACCESS_NONE => get_string('accessnone', 'datafield_report'),
                     self::ACCESS_VIEW => get_string('accessview', 'datafield_report'),
                     self::ACCESS_EDIT => get_string('accessedit', 'datafield_report'));
    }

    /**
     * format a table row in mod.html
     */
    public function format_table_row($name, $label, $text) {
        $label = $this->format_edit_label($name, $label);
        $output = $this->format_table_cell($label, 'c0').
                  $this->format_table_cell($text, 'c1');
        $output = html_writer::tag('tr', $output, array('class' => $name));
        return $output;
    }

    /**
     * format a table cell in mod.html
     */
    public function format_table_cell($text, $class) {
        return html_writer::tag('td', $text, array('class' => $class));
    }

    /**
     * format a label in mod.html
     */
    public function format_edit_label($name, $label) {
        return html_writer::tag('label', $label, array('for' => $name));
    }

    /**
     * format a hidden field in mod.html
     */
    public function format_edit_hiddenfield($name, $value) {
        $params = array('type'  => 'hidden',
                        'name'  => $name,
                        'value' => $value);
        return html_writer::empty_tag('input', $params);
    }

    /**
     * format a text field in mod.html
     */
    public function format_edit_textfield($name, $value, $class, $size=10) {
        $params = array('type'  => 'text',
                        'id'    => 'id_'.$name,
                        'name'  => $name,
                        'value' => $value,
                        'class' => $class,
                        'size'  => $size);
        return html_writer::empty_tag('input', $params);
    }

    /**
     * format a textarea field in mod.html
     */
    public function format_edit_textarea($name, $value, $class, $rows=3, $cols=40) {
        $params = array('id'    => 'id_'.$name,
                        'name'  => $name,
                        'class' => $class,
                        'rows'  => $rows,
                        'cols'  => $cols);
        return html_writer::tag('textarea', $value, $params);
    }

    /**
     * format a select field in mod.html
     */
    public function format_edit_selectfield($name, $values, $default) {
        if (isset($this->field->$name)) {
            $default = $this->field->$name;
        }
        return html_writer::select($values, $name, $default, '');
    }

    /**
     * get list of datafield types (excluding this one)
     * based on /mod/data/field.php
     */
    public function get_datafield_types($exclude=array()) {
        $types = array();
        $plugins = core_component::get_plugin_list('datafield');
        foreach ($plugins as $plugin => $fulldir){
            if ($plugin==$this->type || in_array($plugin, $exclude)) {
                continue;
            }
            $types[$plugin] = get_string('pluginname', 'datafield_'.$plugin);
        }
        asort($types);
        return $types;
    }

    /**
     * display a subfield's settings in mod.html
     */
    public function display_edit_subfield() {
        global $CFG, $DB, $OUTPUT, $PAGE;
        if ($subfolder = $this->subfolder) {
            if (file_exists($subfolder.'/mod.html')) {
                ob_start(array($this, 'prepare_edit_subfield'));
                include($subfolder.'/mod.html');
                ob_end_flush();
            }
        }
    }

    /**
     * convert subfield's full mod.html to an html snippet
     * that can be appended to this report field's mod.html
     */
    public function prepare_edit_subfield($output) {

        // remove surrounding <table> tags
        $search = '/(^\s*<table[^>]*>)|(<\/table[^>]*>\s*$)/i';
        $output = preg_replace($search, '', $output);

        // remove first two rows (field name and description)
        $search = '/^(\s*<tr[^>]*>.*?<\/tr[^>]*>){1,2}/is';
        $output = preg_replace($search, '', $output);

        return trim($output);
    }

    /**
     * add javascript to disable a field if specified conditions are met
     */
    public function js_setup_fields() {
        return '';
    }

    /**
     * determine the id of the form element for the given $field
     */
    public function get_form_element_id($field) {
        $id = 'field_'.$field->id;
        $type = $field->type;
        if ($type=='report') {
            $param = $this->subparam;
            $type = $field->$param;
        }
        if ($type=='checkbox' || $type=='radiobutton') {
            $id .= '_0'; // this id should always exist
        }
        return $id;
    }
}
