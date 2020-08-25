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
$string['pluginname'] = 'Report';

/** more strings */
$string['addedit_help'] = 'The value of this field on the "Add entry" template, which is the page to add a new record to, or edit an existing record from, this database.

* If this field is not required on the "Add entry" template, it can be left blank.
* If it is required but is blank, then the "Search" value for this field will be used instead.';
$string['addedit'] = 'Add entry';
$string['asearch_help'] = 'The value of this field on the "Advanced search" template, which is the page to select records from this database.

* If this field is not required on the "Advanced search" template, it can be left blank.
* If it is required but is blank, then the "Add entry" value for this field will be used instead.';
$string['asearch'] = 'Search';
$string['errorfunctionarguments'] = 'Oops; incorrect arguments for the {$a->name} function. It expects {$a->count} arguments: {$a->description}';
$string['errorfunctionusers'] = 'a format string, and a list of user ids.';
$string['errorunknownfunction'] = 'Oops, unknown function: {$a}';
$string['fieldtypelabel'] = 'Report field';
$string['viewlist_help'] = 'The value of this field on the "View list" template, which is the page to display a list of records from this database.

* If this field is not required on the "View list" template, it can be left blank.
* If it is required but is blank, then the "View single" value for this field will be used instead.';
$string['viewlist'] = 'View list';
$string['viewsingle_help'] = 'The value of this field on the "View single" template, which is the page to display a single record from this database.

* If this field is not required on the "View single" template, it can be left blank.
* If it is required but is blank, then the "View list" value for this field will be used instead.';
$string['viewsingle'] = 'View single';

$string['reportfieldintroduction'] = 'On this page, you can define the output format for this field on the the four main templates in this database - "View list", "View single", "Search" and "Add entry". The output format is specified using functions, in a similar way to how values are calculated in a spreadsheet program, such as Excel. 

The following functions are available: 
* USER

The following constants are available: 
* CURRENT_RECORD
';
