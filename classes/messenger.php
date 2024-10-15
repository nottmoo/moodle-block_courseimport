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

namespace block_courseimport;
use \core\message\message;
use local_uonlib\course_utils;

/**
 * Class for sending messages.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2018 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class messenger {
    /**
     * Sends a message that an import job completed successfully.
     *
     * @global \moodle_database $DB
     * @param int $userid The id of the user the message is to.
     * @param int $targetcourseid The id of the course that content was imported into.
     * @param string $targetcourse The name of the course content was imported into.
     * @param string $sourcecourse the name of the course content was imported from.
     * @return int|bool The integer ID of the new message or false if there was a problem with submitted data
     */
    public static function import_success($userid, $targetcourseid, $targetcourse, $sourcecourse) {
        global $DB;
        $campusmail = course_utils::get_support_email("");
        $fromuser = $DB->get_record('user', array('email' => $campusmail));
        if (!$fromuser) {
            return;
        }
        $context = \context_course::instance($targetcourseid);

        $textparams = array('importto' => $targetcourse, 'importfrom' => $sourcecourse);
        $text = get_string('useremailmessage', 'block_courseimport', $textparams);

        $message = new message();
        $message->component = 'block_courseimport';
        $message->name = 'complete';
        $message->userfrom = $fromuser;
        $message->userto = $userid;
        $message->subject = get_string('useremailsubject', 'block_courseimport');
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml = format_text($text, FORMAT_MARKDOWN);
        $message->smallmessage = '';
        $message->notification = 1;
        $message->contexturl = $context->get_url();
        $message->contexturlname = $context->get_context_name(false);
        $message->courseid = $targetcourseid;
        return message_send($message);
    }

    /**
     * Sends a message about a failure.
     *
     * @param string $subject
     * @param string $message
     * @param int $courseid The course that was being imported into
     * @param int|null $userid
     */
    public static function failure(string $subject, string $message, int $courseid, ?int $userid = null): bool {
        global $DB;
        $campusmail = course_utils::get_support_email(""); // This will get default support email, which is UK support email.
        $campus = $DB->get_record('user', ['email' => $campusmail]);
        if (!$campus) {
            // No support email user.
            return false;
        }

        $context = \context_course::instance($courseid);

        $msg = new message();
        $msg->component = 'block_courseimport';
        $msg->name = 'problem';
        $msg->userfrom = $campus;
        $msg->subject = $subject;
        $msg->fullmessage = $message;
        $msg->fullmessageformat = FORMAT_MARKDOWN;
        $msg->fullmessagehtml = format_text($message, FORMAT_MARKDOWN);
        $msg->smallmessage = '';
        $msg->notification = 1;
        $msg->contexturl = $context->get_url();
        $msg->contexturlname = $context->get_context_name(false);
        $msg->courseid = $courseid;

        if ($userid !== null) {
            // Send e-mail to user.
            $msg->userto = $userid;
        } else {
            // Send e-mail to Campus support.
            $msg->userto = $campus->id;
        }

        // Send the message.
        if ($mailsent = message_send($msg)) {
            return true;
        }
        \block_courseimport\event\email_failed::create_from_users($campus->id, $msg->userto);
        return false;
    }
}
