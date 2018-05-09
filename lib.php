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

defined('MOODLE_INTERNAL') || die();

/** Job waiting to be processed. */
define('BLOCK_COURSEIMPORT_STATE_WAITING', '222222');
/** Block jobs. */
define('BLOCK_COURSEIMPORT_STATE_BLOCK', '444444');
/** Import job finished. */
define('BLOCK_COURSEIMPORT_STATE_FINISHED', '555555');
/** Job is being processed. */
define('BLOCK_COURSEIMPORT_STATE_PROCESSING', '666666');
/** Could not be imported and abandoned job. */
define('BLOCK_COURSEIMPORT_STATE_FAILED', '777777');

/**
 * block_courseimport_changestatus
 *
 * change job status
 *
 * @param int jobid
 * @param string status
 *
 */
function block_courseimport_changestatus($jobid, $status) {
    global $DB;
    $temprecord = new stdClass();
    $temprecord->id = $jobid;
    $temprecord->status = $status;
    $temprecord->timemodified = time();
    $DB->update_record('block_courseimport', $temprecord);
}

/**
 * block_courseimport_abandonjob
 *
 * abandon job
 *
 * @param array $abandonjobs
 */
function block_courseimport_abandonjob($abandonjobs) {
    global $DB;
    if (count($abandonjobs) > 0) {
        foreach ($abandonjobs as $abandon) {
            $jobid = $abandon->id;
            $temprecord = new stdClass();
            $temprecord->id = $jobid;
            $temprecord->status = BLOCK_COURSEIMPORT_STATE_FAILED;
            $temprecord->timemodified = time();
            $DB->update_record('block_courseimport', $temprecord);
            unset($temprecord);
            $timenow = date('Y-m-d H:i:s');
            $courseid = $abandon->courseid;
            $targetcourseid = $abandon->targetcourseid;
            $userid = $abandon->userid;
            $message = get_string('abandonedmessage', 'block_courseimport',
                    array(
                        'timenow' => $timenow,
                        'jobid' => $jobid,
                        'userid' => $userid,
                        'courseid' => $courseid,
                        'targetcourseid' => $targetcourseid
                    ));
            $subject = get_string('alertemailsubject', 'block_courseimport');
            $isemail= block_courseimport_sendemail($subject, $message);
            if (!$isemail) {
                echo "\n$timenow Error! Jobid: $jobid. Failed to send email to admin. Email message: .\n$message\n";
            }
        }
    }
}

/**
 * Find file's size
 *
 * @param resource id, also is coursemodule's instance id
 * @return fileinfo object
 */
function block_courseimport_findfilesize($id) {
    global $DB, $COURSE;
    $fileinfo = new stdClass;
    $fileinfo->fsize = "";
    $fileinfo->ftype = "";
    $context = null;

    if (!$cm = get_coursemodule_from_instance('resource', $id)) {
        return false;
    } else {
        $cm = get_coursemodule_from_instance('resource', $id);
        $resource = $DB->get_record('resource', array('id' => $cm->instance), '*', MUST_EXIST);
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false); // TODO: this is not very efficient!!
        if (count($files) < 1) {
            resource_print_filenotfound($resource, $cm, $COURSE);
            return false;
        } else {
            $file = reset($files);
            unset($files);
            $fileinfo->fsize = $file->get_filesize();
            $fileinfo->ftype = $file->get_mimetype();
            return $fileinfo;
        }
    }
}

/**
 *  timecheck function : check if time now between param start and end
 *
 * @param string start time
 * @param string start end
 * @return true/false
 */
function block_courseimport_timecheck($start, $end) {
    $time = date("G:i:s");
    $time1 = strtotime($time);
    $resttimefrom = strtotime($start);
    $resttimeto = strtotime($end);
    $midnight = strtotime('midnight');

    if ($resttimefrom < $resttimeto) {
        // When the from time is lower than the to time the current time should be between the values.
        if (($time1 > $resttimefrom ) and ($time1 < $resttimeto)) {
            return true;
        } else {
            return false;
        }
    } else if ($resttimefrom > $resttimeto) {
        // When the from time is greater than the to time the current time should be outside the gap between them.
        if ((($time1 > $resttimefrom)  and ($time1 > $resttimeto)) or (($time1 < $resttimefrom)  and ($time1 < $resttimeto))) {
            return true;
        } else {
            return false;
        }
    } else if ($resttimefrom == $midnight && $resttimeto == $midnight) {
        // Assume midnight - midnight means the whole day.
        return true;
    } else {
        return false; // From and to are equal, assume no time.
    }
}

/**
 * block_courseimport_sendemail
 *
 * Send email to Moodle admin or to a user
 *
 * @param string $subject
 * @param string $message
 * @param int $userid
 * @return bool
 */
function block_courseimport_sendemail($subject, $message, $userid = null) {
    global $DB;
    $campusmail = local_uonlib_courselib::get_support_email(""); // This will get default support email, which is UK support email.

    if ($campus = $DB->get_record('user', array('email' => $campusmail))) {
        $campussupport = $campus->id;
        if ($userid !== null) { // Email to a user, not learning-support.
            $touser = $DB->get_record('user', array('id' => $userid));
            if ($mailsent = email_to_user($touser, $campus, $subject, $message)) {
                return true;
            } else {
                block_courseimport_logemailfail($campussupport, $userid);
                return false;
            }
        } else {
            if ($mailsent = email_to_user($campus, $campus, $subject, $message)) {
                return true;
            } else {
                block_courseimport_logemailfail($campussupport, $campussupport);
                return false;
            }
        }
    } else {
        return false;
    }
}

/**
 * block_courseimport_logemailfail
 *
 * Log error of moodle core function email_to_user()
 *
 * @param string $adminuserid
 * @param string $userid
 */
function block_courseimport_logemailfail($adminuserid, $userid) {
    $subject = get_string('emailfailure', 'block_courseimport');
    $messagetext = $subject;
    // Trigger event for failing to send email.
    $event = \block_courseimport\event\email_failed::create(array(
        'context' => context_system::instance(),
        'userid' => $adminuserid,
        'relateduserid' => $userid,
        'other' => array(
            'subject' => $subject,
            'errorinfo' => $messagetext,
            'message' => $messagetext)));
    $event->trigger();
}
