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
 * @package    data
 * @subpackage datafield_report
 * @copyright  2015 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

define('AJAX_SCRIPT', true);

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/mod/data/lib.php');
require_once($CFG->dirroot.'/mod/data/field/report/field.class.php');

// check we are a valid user
require_sesskey();

if ($id = optional_param('id', 0, PARAM_INT)) {
    $cm = get_coursemodule_from_id('data', $id, 0, false, MUST_EXIST);
} else if ($d = optional_param('d', 0, PARAM_INT)) {
    $cm = get_coursemodule_from_instance('data', $d, 0, false, MUST_EXIST);
} else {
    debugging('mod/data/field/report/field.ajax.php: '."\n".
              'Script requires "id" (course module id) or "d" (data id)');
    die;
}
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$instance = $DB->get_record('data', array('id' => $cm->instance), '*', MUST_EXIST);

// check we are logged in
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

$name = 'data'; // we expect an array, but it may be scalar
if (isset($_POST[$name]) && is_array($_POST[$name])) {
    $data = optional_param_array($name, '', PARAM_RAW);
} else if (isset($_GET[$name]) && is_array($_GET[$name])) {
    $data = optional_param_array($name, '', PARAM_RAW);
} else {
    $data = optional_param($name, '', PARAM_RAW);
}
$action = optional_param('a', '', PARAM_ALPHA);

// check we have suitable capability
if ($action == 'displayvalue') {
	require_capability('mod/data:view', $context); // student
} else {
	require_capability('mod/data:manageentries', $context); // teacher
}

switch ($action) {

    case 'displayvalue':
        if ($fieldname = optional_param('f', '', PARAM_ALPHANUM)) {
            $param = optional_param('p', '', PARAM_ALPHANUM);
            $recordid = optional_param('rid', 0, PARAM_INT);
            // optional_param('uid', $USER->id, PARAM_INT);
            if ($field = data_get_field_from_name($fieldname, $instance)) {
                echo $field->display_field($recordid, $param);
            }
        }
        die;
        break;

    case 'checkrecordsexist':
        if ($fieldname = optional_param('f', '', PARAM_ALPHANUM)) {
            $param = optional_param('p', '', PARAM_ALPHANUM);
            $recordid = optional_param('rid', 0, PARAM_INT);
            // optional_param('uid', $USER->id, PARAM_INT);
            if ($field = data_get_field_from_name($fieldname, $instance)) {
                $records = $field->display_field($recordid, $param);
                $records = json_decode($records);
                // add records to the target database using the JSON info
                // e.g. [["presenter_name" : "presenter name 1"], ["presenter_name" : "presenter name 1"]]
                foreach ($records as $record) {
                    // TODO: check that this record exists.
                    // If it doesn't exist, then add it.
                }
            }
        }
        die;
        break;
}