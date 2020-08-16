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
$string['fieldtypelabel'] = 'Report field';
$string['selectfield_help'] = 'Specify the name of the field that you want to be selected and displayed.

By default, the report will assume that the field is in the current database.

To specify a field in another database in this course, put the course module id in front of the field name, thus:

* 123.reportcontent

You can also specify one of the following aggregate functions, for combining the selected values into a single value.

* MIN
* MAX
* SUM
* COUNT
* AVERAGE
* JOIN';
$string['selectfield'] = 'Select';
$string['sortfield_help'] = 'The field on which you wish to sort results. This field is optional, and is usually not necessary.';
$string['sortfield'] = 'Sort by';
$string['wherecondition_help'] = 'The condition used to select records from the target database. 

The condition is experssed as ...

* *field OPERATOR field_or_value*

... where OPERATOR can be one of the following: ...

* =
* >
* >
* <
* <=
* <>
* !=
* IN
* NOT IN
* LIKE
* NOT LIKE
* REGEXP';
$string['wherecondition'] = 'Where';

/** more strings */
