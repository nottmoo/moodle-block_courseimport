<?php
// This file is part of courseimport block in Moodle - http://moodle.org/
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
 * This file contains language strings used in the course import block.
 *
 * @package block_courseimport
 * @copyright University of Nottingham
 * @author Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Course Import';
$string['importlink'] = 'Course Import';
$string['filetype'] = 'File type';
$string['importpending'] = 'Content queued for import';
$string['askroleinfo'] = "You do not have permission to import from the modules below. You should contact the module's owner to ask for the Editing Teacher role if you wish to import from one.";
$string['videofile'] = 'Note:&nbsp;The&nbsp;above&nbsp;video&nbsp;file&nbsp;will&nbsp;not&nbsp;be&nbsp;imported.';
$string['courseimport:addinstance'] = 'Add the course import block to a page';
$string['courseimport:manage'] = 'See and change settings for the course import block';
$string['courseimport:view'] = 'Use the course import block functionality';
$string['time'] = 'Cron time';
$string['alertemailsubject'] = 'Course import error alert';
$string['useremailsubject'] = 'Moodle course import';
$string['useremailmessage'] = 'Your course import job had completed.

* Imported from: {$a->importfrom}
* Imported into: {$a->importto}';
$string['courseimport:manage'] = 'See and change settings for the course import block';
$string['courseimport:view'] = 'Use the course import block functionality';
$string['abandonedmessage'] = '{$a->timenow}, Job abandoned, Jobid={$a->jobid}, Userid={$a->userid}. Import To Course ID:{$a->target}. Import From Course ID:{$a->source}';
$string['precheckfail'] = '{$a->timenow} Error! In Jobid: {$a->jobid}. Prechecks for importing backupfile failed. Import From Course ID:{$a->source} -> Import To Course ID:{$a->target}';
$string['importfail'] = '{$a->timenow} Error! In Jobid: {$a->jobid}. Course import failed. Import From Course ID:{$a->source} -> Import To Course ID:{$a->target}';
$string['alreadyimporting'] = 'There is an import in progress for this course. Please wait for it to complete.';
$string['emailfailure'] = "Course import could not send email to user";
$string['messageprovider:complete'] = 'Confirmation that a course import has completed';
$string['messageprovider:problem'] = 'Messages about errors with a course import';
$string['privacy:export:jobs'] = 'Course import jobs';
$string['privacy:export:status:failed'] = 'Import failed';
$string['privacy:export:status:finished'] = 'Import completed';
$string['privacy:export:status:processing'] = 'Importing';
$string['privacy:export:status:unknown'] = 'Unknown status';
$string['privacy:export:status:waiting'] = 'Waiting for import';
$string['privacy:metadata:block_courseimport'] = 'Stores a list of import jobs that have been queued.';
$string['privacy:metadata:block_courseimport:backupid'] = 'The id of the backup file that has been created.';
$string['privacy:metadata:block_courseimport:source'] = 'The course that data is being exported from.';
$string['privacy:metadata:block_courseimport:status'] = 'The status of the import.';
$string['privacy:metadata:block_courseimport:target'] = 'The course that is being imported into.';
$string['privacy:metadata:block_courseimport:timecreated'] = 'The time the import job was created.';
$string['privacy:metadata:block_courseimport:timemodified'] = 'The last time the job was updated.';
$string['privacy:metadata:block_courseimport:userid'] = 'The user who created the job.';
$string['privacy:metadata:core_backup'] = 'Creates backups of course information to transfer it into another Moodle course.';
$string['inprogress'] = 'Importing content...';
$string['finished'] = 'Import complete';
$string['failed'] = 'Import failed';
