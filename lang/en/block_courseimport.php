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
$string['courseid'] = 'Course id';
$string['courseimport:addinstance'] = 'Add the course import block to a page';
$string['courseimport:bulkrollover'] = 'Use bulk course import in the course import block';
$string['courseimport:manage'] = 'See and change settings for the course import block';
$string['courseimport:view'] = 'Use the course import block functionality';
$string['bulkrollover'] = 'Bulk course import';
$string['bulkrolloverheading'] = 'Bulk course import';
$string['bulkactiveimports'] = 'You have an active bulk course import in progress';
$string['bulkerrortargetnotfound'] = 'No matching target course for {$a} .';
$string['bulkerrorsourcenotfound'] = 'No matching source (previous-year) course for this target.';
$string['bulkpreviewtargetnew'] = 'New (created on confirm)';
$string['bulkpreviewnewtargetheading'] = 'New target course';
$string['bulkrolloversubmit'] = 'Upload and preview';
$string['bulkcsvfile'] = 'CSV file';
$string['bulkcsvrequired'] = 'Please upload a CSV file.';
$string['bulkcsvinvalidtype'] = 'Only CSV files are allowed.';
$string['bulkcsvinvalidheaders'] = 'The CSV file must include columns for course full name, short name, and ID number (column order does not matter).';
$string['bulkcsvinvalidrow'] = 'Row {$a} is missing a full name, short name, or ID number.';
$string['bulkcsvtoobig'] = 'CSV file is larger than {$a} bytes.';
$string['bulkpreviewheading'] = 'Bulk course import preview';
$string['bulkpreviewsummary'] = 'Rows: {$a->rows}, resolved: {$a->resolved}, unmatched: {$a->unmatched}, to import: {$a->toimport}, skipped: {$a->skipped}';
$string['bulkpreviewaction'] = 'Action';
$string['bulkpreviewactionimport'] = 'Will import';
$string['bulkskipalreadyimported'] = 'Skip — already imported from this source';
$string['bulkskipalreadyimporting'] = 'Skip — import already in progress';
$string['bulkpreviewresolved'] = 'Resolved pairs';
$string['bulkpreviewerrors'] = 'Rows not matched to courses';
$string['bulkpreviewnoerrors'] = 'No unmatched rows found.';
$string['bulkconfirmsubmit'] = 'Confirm and create jobs';
$string['bulkduplicatetargets'] = 'Duplicate target course IDs are not allowed in one submission.';
$string['bulksubmitcreated'] = 'Bulk course import job #{$a->bulkid} created. Imports queued: {$a->created}, skipped: {$a->skipped}, failed before queue: {$a->failed}.';
$string['bulkqueueskip'] = 'Skipped "{$a->name}": {$a->reason}';
$string['bulkenabledsettingstitle'] = 'Enabled import settings';
$string['bulkenabledsettingsintro'] = 'These settings are applied to the bulk course import profile.';
$string['bulkenabledsettingsnone'] = 'No optional include settings are enabled. You can enable more in course import settings.';
$string['courseimportsettings'] = 'Course import settings';
$string['bulkmaxrowsexceeded'] = 'This CSV has more than the allowed {$a} rows. Please split the file into smaller parts.';
$string['bulkstatuslistheading'] = 'Your recent bulk course imports';
$string['bulkstatusid'] = 'Bulk course import job #{$a}';
$string['bulkstatuscolumnd'] = 'Bulk course import';
$string['bulkstatustotal'] = 'Total imports';
$string['bulkstatuscompleted'] = 'Completed';
$string['bulkstatusfailed'] = 'Failed';
$string['bulkstatusstate'] = 'Status';
$string['bulkstatusstatequeued'] = 'Queued';
$string['bulkstatusstateprocessing'] = 'Processing';
$string['bulkstatusprogresstitleprocessing'] = 'Bulk course import in progress';
$string['bulkstatusstatecompleted'] = 'Completed';
$string['bulkstatusstatefailed'] = 'Failed';
$string['bulkstatusstatepartial'] = 'Partially completed';
$string['bulkstatusprogress'] = 'Progress';
$string['bulkstatusbarlabel'] = '{$a->done} of {$a->total}';
$string['bulkstatusrefreshing'] = 'Bulk course import in progress — progress updates automatically without reloading the page.';
$string['bulkstatusajaxcounts'] = 'Completed: {$a->completed} / Total: {$a->total} / Failed: {$a->failed}';
$string['bulkstatuschildjobs'] = 'Course imports for this bulk course import';
$string['bulkstatusjobid'] = '#';
$string['bulkstatustarget'] = 'Target course';
$string['bulkstatussource'] = 'Source course';
$string['bulkstatusjobstate'] = 'Job status';
$string['bulkstatusview'] = 'View status';
$string['bulkstatusnone'] = 'No bulk course import jobs found.';
$string['bulknochildjobs'] = 'No individual course imports are linked to this bulk course import.';
$string['bulkstatusinvalid'] = 'Bulk course import job not found or access denied.';
$string['bulkresultsheading'] = 'Bulk course import results';
$string['bulkpagination'] = 'Showing {$a->from}–{$a->to} of {$a->total}';
$string['bulkshowallchildjobs'] = 'Show all jobs for this bulk course import';
$string['bulkshowcompletedchildjobs'] = 'Show completed jobs only';
$string['bulkshowfailedchildjobs'] = 'Show failed jobs only';
$string['bulkshowincompletechildjobs'] = 'Show jobs that have not completed';
$string['bulkchildjobfiltersnav'] = 'Filter which jobs are listed';
$string['bulkduplicateshortnames'] = 'Duplicate new course short names in one submission are not allowed.';
$string['bulknocategory'] = 'Could not determine a valid course category for auto-creating the target course.';
$string['bulkinvalidcreaterow'] = 'Each new course row must include full name and short name.';
$string['bulkunknownerror'] = 'Unknown error';
$string['bulkqueuefailure'] = 'Could not queue "{$a->name}": {$a->error}';
$string['bulkcoursedeleted'] = 'Course no longer exists';
$string['bulkpreviewcolrow'] = 'Row';
$string['singleimporttab'] = 'Single course import';
$string['includequestionbank'] = 'Include question bank';
$string['includepermissionoverrides'] = 'Include permission overrides';
$string['includeactivitiesresources'] = 'Include activities and resources';
$string['includeblocks'] = 'Include blocks';
$string['includefiles'] = 'Include files';
$string['includefilters'] = 'Include filters';
$string['includecalendarevents'] = 'Include calendar events';
$string['includegroupsgroupings'] = 'Include groups and groupings';
$string['includecustomfields'] = 'Include custom fields';
$string['includecontentbankcontent'] = 'Include content bank content';
$string['includelegacycoursefiles'] = 'Include legacy course files';
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
$string['privacy:metadata:core_backup'] = 'Creates backups of course information to transfer it into another Moodle course.';
$string['searchcourses'] = 'Search courses';
$string['time'] = 'Cron time';
$string['useremailmessage'] = 'Your course import job had completed.

* Imported from: {$a->importfrom}
* Imported into: {$a->importto}';
$string['useremailsubject'] = 'Moodle course import';
