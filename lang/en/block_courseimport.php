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

$string['abandonedjobmessage'] = '{$a->timenow}, Job abandoned, Jobid={$a->jobid}, Userid={$a->userid}. Import To Course ID:{$a->target}. Import From Course ID:{$a->source}';
$string['alertemailsubject'] = 'Course import error alert';
$string['alreadyimporting'] = 'There is an import in progress for this course. Please wait for it to complete.';
$string['askroleinfo'] = "You do not have permission to import from the modules below. You should contact the module's owner to ask for the Editing Teacher role if you wish to import from one.";
$string['bulkactiveimports'] = 'You have an active bulk course import in progress';
$string['bulkchildjobfiltersnav'] = 'Filter which jobs are listed';
$string['bulkconfirmexpired'] = 'Your bulk course import preview has expired or could not be found. Please upload the CSV file again.';
$string['bulkconfirminemptypairs'] = 'There are no resolved course pairs to import. Both source and target courses must already exist and be identifiable in the CSV.';
$string['bulkconfirmsubmit'] = 'Confirm and create jobs';
$string['bulkcoursedeleted'] = 'Course no longer exists';
$string['bulkcsvfile'] = 'CSV file';
$string['bulkcsvinvalidheaders'] = 'The CSV file must include columns for course full name, short name, and ID number (column order does not matter).';
$string['bulkcsvinvalidrow'] = 'Row {$a} is missing a full name, short name, or ID number.';
$string['bulkcsvinvalidtype'] = 'Only CSV files are allowed.';
$string['bulkcsvrequired'] = 'Please upload a CSV file.';
$string['bulkcsvtoobig'] = 'CSV file is larger than {$a} bytes.';
$string['bulkduplicatetargets'] = 'Duplicate target course IDs are not allowed in one submission.';
$string['bulkenabledsettingsintro'] = 'These General import defaults apply to both bulk and single course imports.';
$string['bulkenabledsettingsnone'] = 'No optional include settings are enabled in General import defaults.';
$string['bulkenabledsettingstitle'] = 'Enabled import settings';
$string['bulkerrorsourcenotfound'] = 'No matching source (previous-year) course for target course #{$a->targetid}.';
$string['bulkerrortargetmismatch'] = 'Course "{$a->shortname}" was found, but its full name or ID number does not match the CSV (expected full name "{$a->fullname}", ID number "{$a->idnumber}").';
$string['bulkerrortargetnotfound'] = 'No course found with short name {$a->shortname}.';
$string['bulkimportdefaultslink'] = 'Open General import defaults';
$string['bulkmaxrowsexceeded'] = 'This CSV has more than the allowed {$a} rows. Please split the file into smaller parts.';
$string['bulknochildjobs'] = 'No individual course imports are linked to this bulk course import.';
$string['bulkpagination'] = 'Showing {$a->from}–{$a->to} of {$a->total}';
$string['bulkpreviewaction'] = 'Action';
$string['bulkpreviewactionimport'] = 'Will import';
$string['bulkpreviewcolrow'] = 'Row';
$string['bulkpreviewerrors'] = 'Rows not matched to courses';
$string['bulkpreviewheading'] = 'Bulk course import preview';
$string['bulkpreviewnoerrors'] = 'No unmatched rows found.';
$string['bulkpreviewresolved'] = 'Resolved pairs';
$string['bulkpreviewsummary'] = 'Rows: {$a->rows}, resolved: {$a->resolved}, unmatched: {$a->unmatched}, to import: {$a->toimport}, skipped: {$a->skipped}';
$string['bulkqueuefailure'] = 'Could not queue "{$a->name}": {$a->error}';
$string['bulkqueueskip'] = 'Skipped "{$a->name}": {$a->reason}';
$string['bulkresultsheading'] = 'Bulk course import results';
$string['bulkrollover'] = 'Bulk course import';
$string['bulkrolloverheading'] = 'Bulk course import';
$string['bulkrolloversubmit'] = 'Upload and preview';
$string['bulkshowallchildjobs'] = 'Show all jobs for this bulk course import';
$string['bulkshowcompletedchildjobs'] = 'Show completed jobs only';
$string['bulkshowfailedchildjobs'] = 'Show failed jobs only';
$string['bulkshowincompletechildjobs'] = 'Show jobs that have not completed';
$string['bulkskipalreadyimported'] = 'Skip — already imported from this source';
$string['bulkskipalreadyimporting'] = 'Skip — import already in progress';
$string['bulkstatusajaxcounts'] = 'Completed: {$a->completed} / Total: {$a->total} / Failed: {$a->failed}';
$string['bulkstatusbarlabel'] = '{$a->done} of {$a->total}';
$string['bulkstatuschildjobs'] = 'Course imports for this bulk course import';
$string['bulkstatuscolumnd'] = 'Bulk course import';
$string['bulkstatuscompleted'] = 'Completed';
$string['bulkstatusfailed'] = 'Failed';
$string['bulkstatusid'] = 'Bulk course import job #{$a}';
$string['bulkstatusinvalid'] = 'Bulk course import job not found or access denied.';
$string['bulkstatusjobid'] = '#';
$string['bulkstatusjobstate'] = 'Job status';
$string['bulkstatuslistheading'] = 'Your recent bulk course imports';
$string['bulkstatusnone'] = 'No bulk course import jobs found.';
$string['bulkstatusprogress'] = 'Progress';
$string['bulkstatusprogresstitleprocessing'] = 'Bulk course import in progress';
$string['bulkstatusrefreshing'] = 'Bulk course import in progress — progress updates automatically without reloading the page.';
$string['bulkstatussource'] = 'Source course';
$string['bulkstatusstate'] = 'Status';
$string['bulkstatusstatecompleted'] = 'Completed';
$string['bulkstatusstatefailed'] = 'Failed';
$string['bulkstatusstatepartial'] = 'Partially completed';
$string['bulkstatusstateprocessing'] = 'Processing';
$string['bulkstatusstatequeued'] = 'Queued';
$string['bulkstatustarget'] = 'Target course';
$string['bulkstatustotal'] = 'Total imports';
$string['bulkstatusview'] = 'View status';
$string['bulksubmitcreated'] = 'Bulk course import job #{$a->bulkid} created. Imports queued: {$a->created}, skipped: {$a->skipped}, failed before queue: {$a->failed}.';
$string['bulksubmitprogress'] = 'Queueing import {$a->current} of {$a->total}';
$string['bulksubmitqueuedone'] = 'Bulk course import jobs have been queued';
$string['bulksubmitqueuing'] = 'Queueing bulk course import jobs';
$string['bulkunknownerror'] = 'Unknown error';
$string['courseid'] = 'Course id';
$string['courseimport:addinstance'] = 'Add the course import block to a page';
$string['courseimport:bulkrollover'] = 'Use bulk course import in the course import block';
$string['courseimport:manage'] = 'See and change settings for the course import block';
$string['courseimport:view'] = 'Use the course import block functionality';
$string['emailfailure'] = "Course import could not send email to user";
$string['failed'] = 'Import failed';
$string['filetype'] = 'File type';
$string['finished'] = 'Import complete';
$string['importfail'] = '{$a->timenow} Error! In Jobid: {$a->jobid}. Course import failed. Import From Course ID:{$a->source} -> Import To Course ID:{$a->target}';
$string['importlink'] = 'Course import';
$string['importpending'] = 'Content queued for import';
$string['inprogress'] = 'Importing content...';
$string['messageprovider:complete'] = 'Confirmation that a course import has completed';
$string['messageprovider:problem'] = 'Messages about errors with a course import';
$string['pluginname'] = 'Course import';
$string['precheckfail'] = '{$a->timenow} Error! In Jobid: {$a->jobid}. Prechecks for importing backupfile failed. Import From Course ID:{$a->source} -> Import To Course ID:{$a->target}';
$string['privacy:export:bulkjobs'] = 'Bulk course import jobs';
$string['privacy:export:jobs'] = 'Course import jobs';
$string['privacy:export:status:failed'] = 'Import failed';
$string['privacy:export:status:finished'] = 'Import completed';
$string['privacy:export:status:processing'] = 'Importing';
$string['privacy:export:status:unknown'] = 'Unknown status';
$string['privacy:export:status:waiting'] = 'Waiting for import';
$string['privacy:metadata:block_courseimport'] = 'Stores a list of import jobs that have been queued.';
$string['privacy:metadata:block_courseimport:backupid'] = 'The id of the backup file that has been created.';
$string['privacy:metadata:block_courseimport:bulk_job_id'] = 'The optional parent bulk course import job id this import belongs to.';
$string['privacy:metadata:block_courseimport:source'] = 'The course that data is being exported from.';
$string['privacy:metadata:block_courseimport:status'] = 'The status of the import.';
$string['privacy:metadata:block_courseimport:target'] = 'The course that is being imported into.';
$string['privacy:metadata:block_courseimport:timecreated'] = 'The time the import job was created.';
$string['privacy:metadata:block_courseimport:timemodified'] = 'The last time the job was updated.';
$string['privacy:metadata:block_courseimport:userid'] = 'The user who created the job.';
$string['privacy:metadata:block_courseimport_bulk_job'] = 'Stores metadata for bulk course import submissions started by a user.';
$string['privacy:metadata:block_courseimport_bulk_job:completed_count'] = 'The number of child imports completed successfully.';
$string['privacy:metadata:block_courseimport_bulk_job:failed_count'] = 'The number of child imports that failed.';
$string['privacy:metadata:block_courseimport_bulk_job:status'] = 'The status of the bulk course import.';
$string['privacy:metadata:block_courseimport_bulk_job:timecreated'] = 'The time the bulk course import was created.';
$string['privacy:metadata:block_courseimport_bulk_job:timemodified'] = 'The last time the bulk course import was updated.';
$string['privacy:metadata:block_courseimport_bulk_job:total_count'] = 'The total number of child imports in the bulk submission.';
$string['privacy:metadata:block_courseimport_bulk_job:userid'] = 'The user who started the bulk course import.';
$string['privacy:metadata:core_backup'] = 'Creates backups of course information to transfer it into another Moodle course.';
$string['searchcourses'] = 'Search courses';
$string['singleimporttab'] = 'Single course import';
$string['time'] = 'Cron time';
$string['useremailmessage'] = 'Your course import job had completed.

* Imported from: {$a->importfrom}
* Imported into: {$a->importto}';
$string['useremailsubject'] = 'Moodle course import';
