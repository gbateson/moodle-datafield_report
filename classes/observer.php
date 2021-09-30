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
 * Handle events that this plugin is interested in.
 *
 * @package    mod_data
 * @subpackage datafield_report
 * @copyright  2015 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

//classes/observer.php
defined('MOODLE_INTERNAL') || die();

/**
 * Event observers supported by this plugin
 *
 * @package    datafield_report
 * @copyright  2021 Gordon Bateson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datafield_report_observer {

    /**
     * Observer for the event course_restored.
     *
     * event class defined in...
     * lib/classes/event/course_restored.php
     *
     * event triggered in ...
     * backup/util/plan/restore_plan.class.php
     *
     * @param \core\event\course_restored $event
     */
    public static function course_restored(\core\event\course_restored $event) {
        $modinfo = get_fast_modinfo($event->objectid); // course id
        foreach ($modinfo->get_cms() as $cmid => $cm) {
            if ($cm->modname == 'data') {
                self::fix_report_fields($cmid);
            }
        }
    }

    /**
     * Observer for the event course_module_created.
     *
     * event class defined in...
     * lib/classes/event/course_module_created.php
     *
     * event triggered when duplicating course module in ...
     * course/lib.php
     *
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        if (isset($event->other) && is_array($event->other)) {
            if ($event->other['modulename'] == 'data') {
                $cmid = $event->objectid;
                self::fix_report_fields($cmid);
            }
        }
    }

    /**
     * Helper method to fix report fields based on their param5 value.
     */
    protected static function fix_report_fields($cmid) {
        global $CFG, $DB;


        // We need the mod/data functions to create data_field_xxx objects
        // using the "data_get_field()" function.
        require_once($CFG->dirroot.'/mod/data/lib.php');

        $cm = $DB->get_record('course_modules', array('id' => $cmid));
        $data = $DB->get_record('data', array('id' => $cm->instance));

        $params = array('dataid' => $data->id, 'type' => 'report');
        if ($fields = $DB->get_records('data_fields', $params)) {

            // check to see if se are doing a restore (usualy we are)
            $restoreid = optional_param('restore', false, PARAM_ALPHANUM);

            $useridfields = array();
            foreach ($fields as $fid => $field) {
                $fields[$fid] = data_get_field($field, $data, $cm);
                if ($restoreid) {
                    $search = preg_quote($field->name, '/');
                    $search = '/USER\("[^"]*", *GET_VALUE\("'.$search.'", *CURRENT_RECORD\)\)/';
                    $useridfields[$fid] = preg_match($search, $field->param2);
                }
            }


            list($search, $params) = $DB->get_in_or_equal(array_keys($fields));
            if ($contents = $DB->get_records_select('data_content', "fieldid $search", $params)) {

                // Initialize object of userids, in case they are needed.
                $userids = datafield_report_observer_userids::get_instance();

                foreach ($contents as $content) {
                    $value = $content->content;
                    $rid = $content->recordid;
                    $fid = $content->fieldid;
                    $format = $fields[$fid]->field->param5;
                    if (is_numeric($value) && ($format == '' || $format == 'default')) {
                        if (array_key_exists($fid, $useridfields) && $useridfields[$fid]) {
                            $value = $userids->map($value);
                        }
                    }
                    $fields[$fid]->update_content_import($rid, $value, 'field_'.$fid);
                }
            }
        }
    }
}

class datafield_report_observer_userids {

    /** the singleton instance of this class */
    private static $instance = null;

    /**
     * array to map old userids (in backup file) to new userids (in this Moodle site)
     * Note that values will be extracted from the backup file only if needed.
     */
    private $userids = null;

    public static function get_instance(){
        if (self::$instance === null){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function map($oldid) {
        global $CFG, $DB, $USER, $restore;

        // Sanity check on the incoming id.
        if (empty($oldid) || (is_numeric($oldid) == false)) {
            return $oldid;
        }

        // Unfortunately, the information to map old userids to userids,
        // that is stored in the "backup_ids_temp" table
        // and also in the restore_dbops::$backupidscache array
        // has already been destroyed by the time we get to this point.

        // Try to create an array to map old ids to username
        // and use the usernames to get the new user ids.

        if ($this->userids === null) {
            $this->userids = array();

            $olduserids = array();

            // Try get the path to the extracted backup file.
            // e.g. /path/to/moodle/data/temp/backup//8ba14eef5a17a8b032e7ee2732bd3a1a
            // Unfortunately, it has already been removed :-(

            $dirpath = $restore->get_controller()->get_plan()->get_basepath();
            $filename = 'users.xml';
            $filepath =  rtrim($dirpath, '/').'/'.$filename;

            if (is_dir($dirpath) && file_exists($filepath)) {

                // file already exists - don't delete it later
                $remove_filepath = false;

            } else if (check_dir_exists($dirpath, true, true)) {

                // ===========================================================
                // This method only works to restore files that were uploaded
                // at the time of the restore.
                // It does NOT work when restoring files from a "backup area".
                // -----------------------------------------------------------
                // The workaround is to download a file from the backup area,
                // and then upload it again using "Import a backup file"
                // ===========================================================

                // file does not exist, so we create it and then remove it later
                $remove_filepath = true;

                // try to locate the mbz file and extract its contents.
                $select = 'contextid = :contextid AND '.
                          'filearea = :filearea AND '.
                          'component = :component AND '.
                          $DB->sql_like('filename', ':filename').' AND '.
                          'mimetype = :mimetype';

                $params = array('contextid' => context_user::instance($USER->id)->id,
                                'filearea' => 'draft',
                                'component' => 'user',
                                'filename' => '%.mbz',
                                'mimetype' => 'application/vnd.moodle.backup');

                if ($file = $DB->get_records_select('files', $select, $params, 'timecreated DESC', '*', 0, 1)) {

                    $file = reset($file);
                    $file = get_file_storage()->get_file($file->contextid,
                                                         $file->component,
                                                         $file->filearea,
                                                         $file->itemid,
                                                         $file->filepath,
                                                         $file->filename);
                    if ($file_packer = get_file_packer($file->get_mimetype())) {
                        $file_packer->extract_to_pathname($file, $dirpath, array($filename));
                    }
                }
            }

            // Hopefully, we now have extracted the "users.xml".
            if (file_exists($filepath) && ($fh = @fopen($filepath, 'r'))) {

                // Instead of parsing all the XML, we just search for the "user" and "username" tags.
                $matchuser = '/<user id="([^"]*)" contextid="([^"]*)">/';
                $matchusername = '/<username>(.*?)<\/username>/';

                $olduserid = 0;
                while (is_string($line = fgets($fh))) {
                    if ($olduserid) {
                        if (preg_match($matchusername, $line, $match)) {
                            $olduserids[$match[1]] = $olduserid;
                            $olduserid = 0;
                        }
                    } else if (preg_match($matchuser, $line, $match)) {
                        $olduserid = $match[1];
                    }
                }
                fclose($fh);
            }

            if ($remove_filepath && file_exists($filepath)) {
                unlink($filepath);
                // Remove the directory too, if it is empty - it should be !!
                if (count(scandir($dirpath)) == 2) {
                    rmdir($dirpath);
                }
            }

            if (count($olduserids)) {
                list($select, $params) = $DB->get_in_or_equal(array_keys($olduserids));
                if ($newuserids = $DB->get_records_select_menu('user', "username $select", $params, '', 'username,id')) {
                    foreach ($newuserids as $username => $newuserid) {
                        $olduserid = $olduserids[$username];
                        $this->userids[$olduserid] = $newuserid;
                    }
                }
            }
        }

        if (array_key_exists($oldid, $this->userids)) {
            return $this->userids[$oldid];
        } else {
            return 0;
        }
    }
}
