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
$string['averageofvalues'] = ' [the average of {$a} values]';
$string['countvote'] = '[{$a} vote] ';
$string['countvotes'] = '[{$a} votes] ';
$string['extraformat_help'] = 'An additional format for this field that may be useful in AJAX.

AJAX calls can be made to the following script, using the parameters given below

* mod/data/field/report/field.ajax.php
  * **sesskey:** the session key for the current user (available via TMP->get_sesskey())
  * **d**: database id (available via TMP.get_url_param("d"))
  * **f**: database field name
  * **p**: parameter name (1 - 5, param1 - param5, "input", "output", "add", "edit", or "view", extra1 - extra3)
  * **uid**: an optional user id that may be needed to generate the AJAX output

You can then initiate the AJAX call using the TMP.ajax object, thus:

    var url = document.location.href;
    url.replace(
        new RegExp("(/mod/data)/.*$"),
        "$1/field/report/field.ajax.php"
    );
    var data = {
        "sesskey": TMP->get_sesskey(),
        "d": TMP.get_url_param("d"),
        "f": "somefieldname",
        "p": "extra1",
        "uid": "somevalue"
    };
    TMP.ajax.post(url, data, function(responsetext){
        // do something with responsetext
    });
';
$string['extraformat1_help'] = $string['extraformat_help'];
$string['extraformat1'] = 'Extra format (1)';
$string['extraformat2_help'] = $string['extraformat_help'];
$string['extraformat2'] = 'Extra format (2)';
$string['extraformat3_help'] = $string['extraformat_help'];
$string['extraformat3'] = 'Extra format (3)';
$string['errorfunctionarguments'] = 'Oops; incorrect arguments for the {$a->name} function. It expects {$a->count} arguments: {$a->description}';
$string['errorfunctionusers'] = 'a format string, and a list of user ids.';
$string['errorunknownfunction'] = 'Oops, unknown function: {$a}';
$string['fieldtypelabel'] = 'Report field';
$string['inputformat_help'] = 'The format of this field on the "Add entry" and "Advanced search" templates.';
$string['inputformat'] = 'Input format';
$string['maximumofvalues'] = ' [the maximum of {$a} values]';
$string['minimumofvalues'] = ' [the minimum of {$a} values]';
$string['outputformat_help'] = 'The format of this field on the "View list" and "View single" templates.';
$string['outputformat'] = 'Output format';
$string['reducearrayresult'] = 'Oops, the "{$a->template}" value for the {$a->fieldname} field returns an array.<br>Use one of the aggregate functions to reduce the array to a single string or value.';
$string['reportfieldintroduction'] = 'On this page, you can define the rules to format this field for input and output. The formats are specified using functions, in a similar way to how values are calculated in a spreadsheet program, such as Excel. Details of the functions are available in the "Mini manual" at the bottom of this page.';
$string['reportfieldfunctions'] = '
#### Shortcuts to commonly used ids and values

*   CURRENT_USER
:   the id of the user who is viewing the curent record

*   RECORD_USER
:   the id of the user who created the current record

*   CURRENT_RECORD
:   the id of the current record

*   CURRENT_RECORDS
:   an array of the ids of records created by the current user in the current database

*   CURRENT_DATABASE
:   the id of the current database activity

*   CURRENT_COURSE
:   the id of the current course

*   CURRENT_USERS
:   an array of userids that the current user can interact with in the current course
:   If the database activity is using separate groups, this list contains only the users in groups to which the current user belongs.

*   CURRENT_GROUPS
:   an array of groupids that the current user belongs to

*   DEFAULT_NAME_FORMAT
:   the default name format for the current language

#### Functions to extract ids and values

*   GET_VALUE(field, record=CURRENT_RECORD)
:   return a single value

*   GET_VALUES(field, records=CURRENT_RECORD)
:   return an array of values

*   GET_FIELD(field, database=CURRENT_DATABASE)
:   return a single fieldid

*   GET_RECORD(database=CURRENT_DATABASE, field, value)
:   return a single recordid

*   GET_RECORDS(database=CURRENT_DATABASE, field, value)
:   return an array of recordids

*   GET_USER_RECORD(database=CURRENT_DATABASE, user=CURRENT_USER)
:   return a single recordid

*   GET_USER_RECORDS(database=CURRENT_DATABASE, user=CURRENT_USER)
:   return an array of recordids

*   GET_DATABASE(database=CURRENT_DATABASE, course=CURRENT_COURSE)
:   return a single dataid

*   GET_GROUP(group=CURRENT_GROUPS, course=CURRENT_COURSE)
:   return a single groupid

*   GET_GROUPS(group=CURRENT_GROUPS, course=CURRENT_COURSE)
:   return an array of groupids

*   GET_GROUP_USERS(group=CURRENT_GROUPS, course=CURRENT_COURSE)
:   return an array of userids

*   GET_COURSE_USERS(course=CURRENT_COURSE)
:   return an array of userids

#### Functions to format ids and values for output

*   USER(format=DEFAULT_NAME_FORMAT, userid=CURRENT_USER)
:   return the formatted user name of the specified user

*   USERS(format=DEFAULT_NAME_FORMAT, userids=CURRENT_USERS)
:   return an array of formatted user names

*   MENU(items)
:   return a drop down menu of items

*   CHECKBOXES(items)
:   return set of checkboxes, one for each item

*   RADIOBUTTONS(items)
:   return set of radio buttons, one for each item

*   TEXT(value)
:   return a &lt;INPUT type="text" value="value" ...&gt; form item for the given value

*   TEXTAREA(value)
:   return a &lt;TEXTAREA&gt; form item for the given value

*   URL(field, record=CURRENT_RECORD)
:   return the url of the field in the specified record
:   "file" and "picture" fields will be converted to the apropriate Moodle URL.

*   LINK(url)
:   format an &lt;a&gt; tag for the given url.

*   IMAGE(url)
:   format an &lt;img&gt; tag for the given url.

*   AUDIO(url)
:   format an HTML5 &lt;audio&gt; tag for the given url.

*   VIDEO(url)
:   return an HTML5 &lt;video&gt; tag for the given url.

*   LIST(values, listtype)
:   return the values formatted as an HTML list depending on the listtype ("UL", "OL" or "DL")

*   SORT(values)
:   return a sorted list of values

*   COUNT_LIST(values)
:   return an HTML list in which each value is preceded by a count of how many times that value occurs, and under which appears the total number of values.

#### Functions to reduce an array to a single item

*   AVG(values)
:   return the average of the values

*   COUNT(values)
:   return the number of values

*   MAX(values)
:   return the maximum value

*   MIN(values)
:   return the minimum value

*   SUM(values)
:   return the sum of the values

*   CONCAT(value1, value2, ...)
:   return a long string produced by concatenating the values together

*   JOIN(shortstring, values)
:   return a long string produced by joining the values together with the shortstring

*   MERGE(list1, list2, ...)
:   return a long list produced by merging the lists together

*   SCORE(scoretype, field, records=CURRENT_RECORD)
:   return the score of the given field in the given records.
:   the "scoretype" can be one of the following: avg, max, min, sum
:   if the field is a menu of text items, the score for each value will be calculated from its position in the menu, highest to lowest.

*   SCORE_AVG(field, records=CURRENT_RECORD)
:   shortcut for SCORE("avg", field, records=CURRENT_RECORD)

*   SCORE_MAX(field, records=CURRENT_RECORD)
:   shortcut for SCORE("max", field, records=CURRENT_RECORD)

*   SCORE_MIN(field, records=CURRENT_RECORD)
:   shortcut for SCORE("min", field, records=CURRENT_RECORD)

*   SCORE_SUM(field, records=CURRENT_RECORD)
:   shortcut for SCORE("sum", field, records=CURRENT_RECORD)

#### Arguments for functions

*   "field" can be one of the following:
:   the numeric id of a field
:   a string that matches the name of a field

*   "database" can be one of the following:
:   a database id number
:   d=99 a database id number
:   id=99 a course module id number
:   cmid=99 a course module id number
:   otherwise, a string that matches the name of database in the specified course.

*   "course" can be one of the following:
:   a course id number
:   otherwise, a string that matches the shortname of a course on this Moodle site.

*   "group" can be one of the following
:   the id of a group in the specified course
:   a string that matches the name of a group in the specified course

*   "user" can be one of the following:
:   the numeric id of a user
:   a string that matches the "Firstname LASTNAME" of a user

*   "format" can be one of the following
:   the word "default", in which case the default for name format for the current language will be used
:   a string containing the names of one or more name fields from a user record e.g. Firstname LASTNAME
    -   If a name field is uppercase in the format string, the value of that name field in the output will also be uppercase, e.g "LASTNAME" produces "SMITH"
    -   If a name field is titlecase in the format string, the value of that name field in the output will also be titlecase, e.g "Lastname" produces "Smith"
    -   If a name field is lowercase in the format string, the value of that name field in the output will also be lowercase, e.g "lastname" produces "smith"
';
$string['sumofvalues'] = ' [the sum of {$a} values]';
$string['totalvote'] = '[{$a} vote in total]';
$string['totalvotes'] = '[{$a} votes in total]';
