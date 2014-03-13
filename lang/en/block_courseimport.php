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
 * This file contains language strings used in the Course life management block
 * @package block_courseimport
 * @copyright University of Nottingham
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Course Import';
$string['importlink'] = 'Course Import';
$string['filetype'] = 'File type';
$string['jobdone'] = 'Your module is now queued for import';
$string['filternotice'] = '<ul><li>To improve the experience of Moodle for students, files larger than {$a->size} MB or any video files will not be imported.  Please download each file and use the [Equella file upload] resource to add these items to your module.</li><li>Please note, forums and Turnitin assignments on the module will not be imported.</li></ul>';
$string['infotime'] = "Here you can set when to run the import job. For example, If you want define 3 time ranges:

 * 02:12-04:15
 * 10:10-14:15
 * 21:30-06:45

then input string should be:

 * 02:12-04:15==10:10-14:15==21:30-06:45

Time ranges are seperated by ==  with no space in string

You can define any number of time ranges.

<b>The imput is not validated. Moodle will ignore any times if there is a error with it.</b>.";
$string['askroleinfo'] = "You do not have permission to import from the modules below. You should contact module's owner to ask for the Editing Teacher role if you wish to import from one.";
$string['videofile'] = 'Note:&nbsp;The&nbsp;above&nbsp;video&nbsp;file&nbsp;will&nbsp;not&nbsp;be&nbsp;imported.';
$string['bigfile'] = 'Note:&nbsp;Above&nbsp;file&nbsp;size&nbsp;is&nbsp;too&nbsp;big&nbsp;to&nbsp;be&nbsp;imported.';
$string['courseimport:addinstance'] = 'Add the course import block to a page';
$string['courseimport:manage'] = 'See and change settings for the course import block';
$string['courseimport:view'] = 'Use the course import block functionality';
$string['max_file_size'] = 'File size limit (MB) ';
$string['time'] = 'Cron time';
