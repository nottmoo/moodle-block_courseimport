<?php
// This file is part of the courseimport block plugin for Moodle
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

defined('MOODLE_INTERNAL') || die;
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');
require_once($CFG->dirroot . '/blocks/courseimport/lib.php');
require_once($CFG->dirroot . '/local/uonlib/uoncronlib.php');


/**
 * Courseimport process class
 *
 * @package    block_courseimport
 * @copyright  University of Nottingham
 * @author     2012 Yijun Xue <yijun.xue@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_courseimport_process {

    /**
     * cron
     *
     * Cron job for courseimport.
     */
    public function cron() {
        set_time_limit(0);
        $log = new local_uonlib_cronlib();
        $timesetting = get_config('block_courseimport', 'crontime');
        if (($timesetting) and ( !empty($timesetting))) {
            $log->logline("Time ranges setting " . $timesetting, false);
            $ranges = explode("==", $timesetting);
            $checkresult = false;
            foreach ($ranges as $range) {
                if (preg_match('/^(([0-1][0-9]|[2][0-3]):([0-5][0-9])-([0-1][0-9]|[2][0-3]):([0-5][0-9]))/', $range) === 1) {
                    $rlist = explode("-", $range);
                    $checkresult += block_courseimport_timecheck($rlist[0], $rlist[1]);
                } else {
                    $log->logline("Process stopped as time range setting ( $range ) is not in right format", false);
                    die();
                }
            }
            if ($checkresult == 0) {
                $log->logline("Current time outside of operating hours. Operating hours are: ( $timesetting ). Process stopped.", false);
                die();
            }
        }
        $log->logline("Current time is within operating hours. Starting process", false);
        $argument1 = null;
        if (isset($argv[1])) {
            $argument1 = $argv[1];
            $log->logline("$argument1 has been passed to the process.\n0=Process stop. 1= Process start", false);

            if ($argument1 === 0) {
                $table = 'block_courseimport';
                $select = "status = :status";
                $counter = $DB->count_records_select($table, $select, array('status' => BLOCK_COURSEIMPORT_STATE_WAITING));
                if ($counter > 0) {
                    // Set all waiting jobs to blocked.
                    $DB->set_field('block_courseimport', 'status', BLOCK_COURSEIMPORT_STATE_BLOCK, array('status' => BLOCK_COURSEIMPORT_STATE_WAITING));
                    $log->logline("Process has been blocked manually, $counter jobs in queue."
                            . "\nTo unblock run php var/www/blocks/courseimport/newbackup.php 1 at the command line.", false);
                } else {
                    $log->logline("There are no jobs in the queue. Cannot block if there are no jobs", false);
                }
            }
            if ($argument1 === 1) {
                // Set all blocked jobs to waiting.
                $DB->set_field('block_courseimport', 'status', BLOCK_COURSEIMPORT_STATE_WAITING, array('status' => BLOCK_COURSEIMPORT_STATE_BLOCK));
                $log->logline("Process will start at the next CRON", false);
            }
            die();
        }
        // Start jobs.
        $countstopjobs = $DB->count_records('block_courseimport', array('status' => BLOCK_COURSEIMPORT_STATE_BLOCK));
        // If a job still is processiong status, should be abandoned.
        $abandonjobs = $DB->get_records('block_courseimport', array('status' => BLOCK_COURSEIMPORT_STATE_PROCESSING));
        $results = $DB->get_records('block_courseimport', array('status' => BLOCK_COURSEIMPORT_STATE_WAITING), 'id');
        // Need countstopjobs to avoid execute any new added job.
        if (($countstopjobs > 0)) {
            $log->logline("Process has been blocked manually and will not run until it is unblocked."
                    . "\nTo unblock run php var/www/blocks/courseimport/newbackup.php 1 at the command line.", false);
            die();
        } else {
            // Check if any job's status is 666666 or failed jobs, email admain and abandon.
            block_courseimport_abandonjob($abandonjobs);

            foreach ($results as $job) {
                $log->logline("Process will start in 20 seconds.", false);
                sleep(20);
                $jobid = $job->id;
                $courseid = $job->courseid;
                $coursecontext = context_course::instance($courseid);
                $contextid = $coursecontext->id;
                $importid = $job->backupid;
                $targetcourseid = $job->targetcourseid;
                $backupid = $importid;
                // The target method for the restore (adding or deleting).
                $restoretarget = 1;
                $userid = $job->userid;
                // Start processing, successfully will chnage to 555555, otherwise abandon and email admin.
                block_courseimport_changestatus($jobid, BLOCK_COURSEIMPORT_STATE_PROCESSING);
                $log->logline("Jobid:$jobid--Userid:$userid\nImport To Course ID:$courseid"
                        . "\nImport From Course ID:$targetcourseid,\nCreating backup for course ID:$targetcourseid now.", false);

                $bc = backup_ui::load_controller($importid);
                $backup = new block_courseimport_import_ui($bc, array('importid' => $importid, 'target' => $restoretarget));
                $backup->execute();
                $backup->destroy();
                unset($backup);
                $tempdestination = $CFG->tempdir . '/backup/' . $backupid;
                if (!file_exists($tempdestination) || !is_dir($tempdestination)) {
                    $log->logline("Error, could not find file in CFG->tempdir/backup folder, "
                            . "Userid:$userid--ImportToCourseid:$courseid ---ImportFromCourseid:$targetcourseid", false);
                    $log->logline(get_string('unknownbackupexporterror', 'error'), false); // Shouldn't happen ever.
                    die();
                }
                list($context, $course, $cm) = get_context_info_array($contextid);
                $rc = new restore_controller($backupid, $course->id, backup::INTERACTIVE_YES, backup::MODE_IMPORT, $userid, 1);
                // Convert the backup if required.... it should NEVER happed.
                if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                    $rc->convert();
                }
                // Mark the UI finished.
                $rc->finish_ui();
                // Execute prechecks.
                if (!$rc->execute_precheck()) {
                    $precheckresults = $rc->get_precheck_results();
                    if (is_array($precheckresults) && !empty($precheckresults['errors'])) {
                        $message = get_string('precheckfail', 'block_courseimport', array(
                            'timenow' => date('Y-m-d H:i:s'),
                            'jobid' => $jobid,
                            'targetcourseid' => $targetcourseid,
                            'courseid' => $courseid
                        ));
                        $log->logline($message, false);
                        // Send email to Moodle admin.
                        $subject = get_string('alteremailsubject', 'block_courseimport');
                        $isemail = block_courseimport_sendemail($subject, $message);
                        if (!$isemail) {
                            $log->logline("Error! Jobid: $jobid. "
                                    . "Failed to send email to admin. Content of message below.\n$message", false);
                        }
                    }
                } else {
                    $message = null;
                    // Execute the restore.
                    try {
                        $rc->execute_plan();
                    } catch (Exception $e) {
                        // need to abandon this job.
                        block_courseimport_changestatus($jobid, BLOCK_COURSEIMPORT_STATE_FAILED);
                        $message = $e->getMessage();
                        $message .= get_string('importfail', 'block_courseimport', array(
                            'timenow' => date('Y-m-d H:i:s'),
                            'jobid' => $jobid,
                            'targetcourseid' => $targetcourseid,
                            'courseid' => $courseid
                        ));
                        $log->logline("Error! Jobid: $jobid " . "\n$message", false);
                    }

                    $rc->destroy(); // Always call these.
                    fulldelete($tempdestination);

                    $importto = $DB->get_field('course', 'fullname', array('id' => $courseid));
                    $importfrom = $DB->get_field('course', 'fullname', array('id' => $targetcourseid));
                    $subject = get_string('useremailsubject', 'block_courseimport');

                    if ($message === null) {
                        block_courseimport_changestatus($jobid, BLOCK_COURSEIMPORT_STATE_FINISHED);
                        $log->logline("Success in Jobid: $jobid. "
                                . "Import is complete.\nImport From Course ID:$targetcourseid -> Import To Course ID:$courseid.", false);
                        $message = get_string('useremailmessage', 'block_courseimport', array('importto' => $importto, 'importfrom' => $importfrom));
                        $isemail = block_courseimport_sendemail($subject, $message, $userid);
                    } else {
                        $isemail = block_courseimport_sendemail($subject, $message); // Send error to Moodle admin.
                    }
                    if (!$isemail) {
                        $log->logline("Error! Jobid: $jobid. "
                                . "Failed to send email. Content of message below.\n$message", false);
                    }
                }
            }
        }
    }
}