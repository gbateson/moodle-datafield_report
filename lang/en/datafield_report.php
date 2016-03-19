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
 * Strings for the "datafield_report" component, language="en", branch="master"
 *
 * @package    data
 * @subpackage datafield_report
 * @copyright  2015 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

/** required strings */
$string['pluginname'] = 'Report field';

/** more string */
$string['accessnone'] = 'Hidden from non-managers';
$string['accessview'] = 'Viewable by non-managers';
$string['accessedit'] = 'Editable by non-managers';
$string['disabledif'] = 'Conditions for disabling this field';
$string['fixlangpack'] = '**The Report field is not yet properly installed**

Please append language strings for the Report field to Database language file:

* EDIT: {$a->langfile}
* ADD: $string[\'report\'] = \'Report\';
* ADD: $string[\'namereport\'] = \'Report field\';

Then purge the Moodle caches:

* Reportistration -> Site reportistration -> Development -> Purge all caches

See {$a->readfile} for more details.';