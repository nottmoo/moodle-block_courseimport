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
define('CLI_SCRIPT', true);
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');
require_once($CFG->dirroot . '/blocks/courseimport/lib.php');

set_time_limit(0);
raise_memory_limit(MEMORY_EXTRA);

echo "\n".date('Y-m-d H:i:s')." Process woken by CRON";
$timesetting = get_config('block_courseimport', 'crontime');
if (($timesetting) and (!empty($timesetting))) {
    echo "Time ranges setting " . $timesetting . "\n";
    $ranges = explode("==", $timesetting);
    $checkresult = false;
    foreach($ranges as $range) {
         if(preg_match('/^(([0-1][0-9]|[2][0-3]):([0-5][0-9])-([0-1][0-9]|[2][0-3]):([0-5][0-9]))/' ,$range)===1) {
            $rlist = explode("-", $range);
            $checkresult += block_courseimport_timecheck($rlist[0],$rlist[1]);
        } else {
            echo "\n".date('Y-m-d H:i:s')." Process stopped as time range setting ( $range )is not in right format\n";
            die();
        }
      }
    if ($checkresult == 0 ) {
        echo "\n".date('Y-m-d H:i:s')." Current time outside of operating hours. Operating hours are: ( $timesetting ). Process stopped. \n";
        die();
    }
}

echo "\n".date('Y-m-d H:i:s')."  Current time is within operating hours. Starting process\n";
$argument1 = null;
if (isset($argv[1])) {
    $argument1 = $argv[1]; 
    // The file can be called with an argument of 0 or 1. If 0 block processing, if 1 unblock processing.
    // Status 222222:job waiting.
    // Status 444444:block jobs.
    // Status 555555: a course import job finished.
    // Status 666666: job in processing.
    // Status 777777: could not be imported and abandoned job, email to admin for import manully and log details.
    echo "\n".date('Y-m-d H:i:s')." $argument1 has been passed to the process.\n 0=Process stop. 1= Process start \n";

    if ($argument1 === 0) {
        $table = 'block_courseimport';
        $select = "status = :status";
        $counter = $DB->count_records_select($table, $select, array('status' => BLOCK_COURSEIMPORT_STATE_WAITING));
        if ($counter > 0) {
            // Set all waiting jobs to blocked.
            $DB->set_field('block_courseimport', 'status', BLOCK_COURSEIMPORT_STATE_BLOCK, array('status' => BLOCK_COURSEIMPORT_STATE_WAITING));
            echo "\n".date('Y-m-d H:i:s')." Process has been blocked manually, $counter jobs in queue."
                    . "\nTo unblock run php var/www/blocks/courseimport/newbackup.php 1 at the command line.\n";
        } else {
            echo "\n".date('Y-m-d H:i:s')." There are no jobs in the queue. Cannot block if there are no jobs";
        }
    }
    if ($argument1 === 1) {
        // Set all blocked jobs to waiting.
        $DB->set_field('block_courseimport', 'status', BLOCK_COURSEIMPORT_STATE_WAITING, array('status' => BLOCK_COURSEIMPORT_STATE_BLOCK));
        echo "\n".date('Y-m-d H:i:s')." Process will start at the next CRON\n";
    }
    die();
}
// Start jobs.
$countstopjobs = $DB->count_records('block_courseimport', array('status' => BLOCK_COURSEIMPORT_STATE_BLOCK));
// If a job still is processiong status, should be abandoned.
$abandonjobs = $DB->get_records('block_courseimport', array('status' => BLOCK_COURSEIMPORT_STATE_PROCESSING));
$results = $DB->get_records('block_courseimport', array('status' => BLOCK_COURSEIMPORT_STATE_WAITING), 'id');

// Need countstopjobs to avoid execute any new added job.
if ( !empty($results) && ($countstopjobs > 0) ) {
    echo "\n".date('Y-m-d H:i:s')." Process has been blocked manually and will not run until it is unblocked."
            . "\nTo unblock run php var/www/blocks/courseimport/newbackup.php 1 at the command line.\n";
    die();
} else {
    // Check if any job's status is 666666 or failed jobs, email admain and abandon.
    block_courseimport_abandonjob($abandonjobs); 
    
    foreach ($results as $job) {
        echo "\n".date('Y-m-d H:i:s')." Process will start in 20 seconds.\n";
        sleep(20);
        $courseid = $job->courseid;
        $coursecontext = context_course::instance($courseid);
        $contextid = $coursecontext->id;
        $importid = $job->backupid;
        $targetcourseid = $job->targetcourseid;
        $backupid = $importid;
        // The target method for the restore (adding or deleting)
        $restoretarget = 1;
        $userid = $job->userid;

        // Start processing, successfully will chnage to 555555, otherwise abandon and email admin.
        block_courseimport_changestatus($jobid, BLOCK_COURSEIMPORT_STATE_PROCESSING);
        echo "\n".date('Y-m-d H:i:s')." Jobid:$jobid--Userid:$userid\nImport To Course ID:$courseid"
                . "\nImport From Course ID:$targetcourseid,\nCreating backup for course ID:$targetcourseid now.\n";
        
        $bc = backup_ui::load_controller($importid);
        $backup = new block_courseimport_import_ui($bc, array('importid' => $importid, 'target' => $restoretarget));
        $backup->execute();
        $backup->destroy();
        unset($backup);
        $tempdestination = $CFG->tempdir . '/backup/' . $backupid;
        if (!file_exists($tempdestination) || !is_dir($tempdestination)) {
            echo "\n".date('Y-m-d H:i:s')." Error, could not find file in CFG->tempdir/backup folder, "
                    . "Userid:$userid--ImportToCourseid:$courseid ---ImportFromCourseid:$targetcourseid \n";
            print_error('unknownbackupexporterror'); // Shouldn't happen ever.
            die();
        }

        list($context, $course, $cm) = get_context_info_array($contextid);
        $rc = new restore_controller($backupid, $course->id, backup::INTERACTIVE_YES, backup::MODE_IMPORT, $userid, 1);
        // Mark the UI finished.
        $rc->finish_ui();
        // Execute prechecks.
        if (!$rc->execute_precheck()) {
            $precheckresults = $rc->get_precheck_results();
            if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                $message = get_string('precheckfail', 'block_courseimport',
                    array(
                        'timenow' => date('Y-m-d H:i:s'),
                        'jobid' => $jobid,
                        'targetcourseid' => $targetcourseid,
                        'courseid' => $courseid
                        ));
                echo "\n".$message."\n";
                // Send email to Moodle admin.
                $subject =  get_string('alteremailsubject', 'block_courseimport');
                $isemail = block_courseimport_sendemail($subject, $message);
                if (!$isemail) {
                    echo "\n".date('Y-m-d H:i:s')." Error! Jobid: $jobid. "
                            . "Failed to send email to admin. Content of message below.\n$message\n";
                }
            }
        } else {
            // Execute the restore.
            $rc->execute_plan();
            $rc->destroy();
            fulldelete($tempdestination);
            block_courseimport_changestatus($jobid, BLOCK_COURSEIMPORT_STATE_FINISHED);
            echo "\n".date('Y-m-d H:i:s')." Success in Jobid: $jobid. "
                    . "Import is complete.\nImport From Course ID:$targetcourseid -> Import To Course ID:$courseid.\n";
            // Send email to user.
            $importto= $DB->get_field('course', 'fullname', array('id' => $courseid));
            $importfrom= $DB->get_field('course', 'fullname', array('id' => $targetcourseid));
            $subject =  get_string('useremailsubject', 'block_courseimport');
            $message = get_string('useremailmessage', 'block_courseimport',
                    array('importto' => $importto, 'importfrom' => $importfrom));
            $isemail = block_courseimport_sendemail($subject, $message, $userid);
            if (!$isemail) {
                echo "\n".date('Y-m-d H:i:s')." Error! Jobid: $jobid. "
                        . "Failed to send email to user to inform of success. Content of message below.\n$message\n";
            }
        }
    }
}