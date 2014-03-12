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
$string['filternotice'] = "</br><ul><b><li>To improve the experience of Moodle for students, files larger than ### MB or any video files will not be imported.  Please download each file and use the [Equella file upload] resource to add these items to your module.</li><li>Please note, no forums nor Turnitin assignments on the module will be imported</li></b></ul></br>";
$string['infotime'] = "Here you can set when to run the import job.</br> For example, If you want define 3 time ranges</br>02:12-04:15</br>10:10-14:15</br>21:30-06:45</br> then input string should be </br>02:12-04:15==10:10-14:15==21:30-06:45</br> Time ranges are seperated by ==  and no space in string</br> You can define any amount of time ranges.</br><b>BUT Moodle could not help you to check typo</br> Moodle will ignro any times if there is a typo in it.</b>.";
$string['askroleinfo'] = "<h2 class='header'>You dont have permission on below modules, You could contact module's owner to ask a Editting Teacher role on a module to do import.</h2>";
$string['videofile'] = 'Note:&nbsp;The&nbsp;above&nbsp;video&nbsp;file&nbsp;will&nbsp;not&nbsp;be&nbsp;imported.';
$string['bigfile'] = 'Note:&nbsp;Above&nbsp;file&nbsp;size&nbsp;is&nbsp;too&nbsp;big&nbsp;to&nbsp;be&nbsp;imported.';
$string['courseimport:manage'] = 'See and change settings for the course import block';
$string['courseimport:view'] = 'Use the course import block functionality';
