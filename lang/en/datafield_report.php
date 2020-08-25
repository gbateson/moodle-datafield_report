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

$string['reportfieldintroduction'] = 'On this page, you can define the output format for this field on the the four main templates in this database - "View list", "View single", "Search" and "Add entry". The output format is specified using functions, in a similar way to how values are calculated in a spreadsheet program, such as Excel.';
$string['reportfieldfunctions'] = '
#### Shortcuts to commonly used ids and values

*   CURRENT_USER
:   the id of the current user, i.e. the user currently looking at the database

*   CURRENT_RECORD
:   the id of the current record

*   CURRENT_DATABASE
:   the id of the current database activity

*   CURRENT_COURSE
:   the id of the current course

*   CURRENT_USERS
:   an array of userids that the current user can interact with in the current course.
:   if the activity is using separate groups, then this list contains only the users in the current group.

*   CURRENT_RECORDS
:   an array of record ids that the current user can access.

*   CURRENT_GROUPS
:   an array of group ids that the current can access

*   DEFAULT_NAME_FORMAT
:   the default name format for the current language

#### Functions to extract ids and values

*   GET_DATABASE(database=CURRENT_DATABASE, course=CURRENT_COURSE)
:   return a single dataid

*   GET_FIELD(field, databaseid)
:   return a single fieldid

*   GET_RECORD(database=CURRENT_DATABASE, user=CURRENT_USER)
:   return a single recordid

*   GET_RECORDS(database=CURRENT_DATABASE, user=CURRENT_USER)
:   return an array of recordids

*   GET_VALUE(field, record=CURRENT_RECORD)
:   return a single value

*   GET_VALUES(field, records=CURRENT_RECORD)
:   return an array of values

*   GET_GROUP(group=CURRENT_GROUPS, course=CURRENT_COURSE)
:   return a single groupid

*   GET_GROUPS(group=CURRENT_GROUPS, course=CURRENT_COURSE)
:   return an array of groupids

*   GET_GROUP_USERS(group=CURRENT_GROUPS, course=CURRENT_COURSE)
:   return an array of userids

*   GET_COURSE_USERS(course=CURRENT_COURSE)
:   return an array of userids

#### Functions to format ids and values

*   USER(format=DEFAULT_FORMAT, userid=CURRENT_USER)
:   return the formatted user name of the specified user

*   USERS(format=DEFAULT_FORMAT, userids=CURRENT_USERS)
:   return an array of formatted user names

*   MENU(items)
:   return a drop down menu of items

*   CHECKBOXES(items)
:   return set of checkboxes, one for each items

*   RADIOBUTTONS(items)
:   return set of radio buttons, one for each items

*   VIDEO(url)
:   format the URL as a HTML5 &lt;video&gt; tag

*   AUDIO(url)
:   format the URL as a HTML5 &lt;audio&gt; tag

*   IMAGE(url)
:   format the URL as a &lt;img&gt; tag

*   MENU(USERS("Firstname LASTNAME", GET_GROUP_USERS()))

#### Arguments for functions

*   course" can be one of the following:
:   a course id number
:   otherwise, a string that matches the shortname of course on this Moodle site.

*   "database" can be one of the following:
:   a database id number
:   d=99 a database id number
:   id=99 a course module id number
:   cmid=99 a course module id number
:   otherwise, a string that matches the name of database in the specified course.

*   "field" can be one of the following:
:   the numeric id of a field
:   a string that matches the name of a field

*   "user" can be one of the following:
:   the numeric id of a user
:   a string that matches the "Firstname LASTNAME" of a user

*   "group" can be one of the following
:   the id of a group in the specified course
:   a string that matches the name of a group in the specified course

*   "format" can be one of the following
:   the word "default", in which case the default for name format for the current language will be used 
:   a string containing the names of one or more name fields from a user record e.g. Firstname LASTNAME
    -   If a name field is UPPERCASE in the format string, the value of that name field in the output will also be UPPERCASE
    -   If a name field is Titlecase in the format string, the value of that name field in the output will also be Titlecase
    -   If a name field is lowercase in the format string, the value of that name field in the output will also be lowercase
';
