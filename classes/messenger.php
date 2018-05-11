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

defined('MOODLE_INTERNAL') || die();

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
        $campusmail = \local_uonlib_courselib::get_support_email("");
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
}
