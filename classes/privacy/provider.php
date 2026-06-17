<?php
// This file is part of the course import block for Moodle
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

namespace block_courseimport\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use block_courseimport\job;

/**
 * Privacy provider for the course import block.
 *
 * @package    block_courseimport
 * @subpackage privacy
 * @copyright  2018 University of Nottingham
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        // Sub systems used.
        $collection->add_subsystem_link('core_backup', [], 'privacy:metadata:core_backup');
        // Personal data stored in the database.
        $jobs = [
            'source' => 'privacy:metadata:block_courseimport:source',
            'target' => 'privacy:metadata:block_courseimport:target',
            'userid' => 'privacy:metadata:block_courseimport:userid',
            'bulk_job_id' => 'privacy:metadata:block_courseimport:bulk_job_id',
            'backupid' => 'privacy:metadata:block_courseimport:backupid',
            'status' => 'privacy:metadata:block_courseimport:status',
            'timecreated' => 'privacy:metadata:block_courseimport:timecreated',
            'timemodified' => 'privacy:metadata:block_courseimport:timemodified',
        ];
        $collection->add_database_table('block_courseimport', $jobs, 'privacy:metadata:block_courseimport');
        // Does not export data from Moodle to another system.
        // Does not store any user preferences.
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        $contextlist->set_component('block_courseimport');
        if ($DB->record_exists('block_courseimport', ['userid' => $userid])) {
            $contextlist->add_user_context($userid);
        }
        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        // There should only be a single context, for the user.
        $context = $contextlist->current();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $user = $contextlist->get_user();
        $sql = "SELECT ci.*, sc.fullname AS sourcename, tc.fullname AS targetname
                  FROM {block_courseimport} ci
             LEFT JOIN {course} sc ON sc.id = ci.source
             LEFT JOIN {course} tc ON tc.id = ci.target
                 WHERE ci.userid = :userid";
        $params = ['userid' => $user->id];
        $records = $DB->get_records_sql($sql, $params);
        if (!empty($records)) {
            $subcontext = [get_string('privacy:export:jobs', 'block_courseimport')];
            $jobs = (object) array_map([__CLASS__, 'transform_job'], $records);
            writer::with_context($context)->export_data($subcontext, $jobs);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        // Check that this is a context_module.
        if (!$context instanceof \context_user) {
            return;
        }
        static::delete_user_data($context->instanceid);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        // There should only be a single context, for the user.
        $context = $contextlist->current();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        static::delete_user_data($contextlist->get_user()->id);
    }

    /**
     * Get users who have data within a context.
     *
     * @param $userlist The userlist containing the list of users who have data in this context/plugin combination.
     *
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }

        $params = [
          'contextid' => $context->id,
          'contextuser' => CONTEXT_USER,
        ];

        $sql = "SELECT bc.userid AS userid
                  FROM {block_courseimport} bc
                  JOIN {context} ctx ON ctx.instanceid = bc.userid
                       AND ctx.contextlevel = :contextuser
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $userids = $userlist->get_userids();
        $context = $userlist->get_context();
        if ($context instanceof \context_user) {
            foreach ($userids as $id) {
                static::delete_user_data($id);
            }
        }
    }

    /**
     * Deletes only jobs that have been processed for a single user.
     *
     * We must not delete records that are being processed as that could break a running import.
     * We should not delete jobs until they have been processed.
     *
     * @param int $userid
     * @return void
     */
    protected static function delete_user_data(int $userid) {
        global $DB;
        $exclude = [job::STATE_PROCESSING, job::STATE_WAITING];
        list($sql, $params) = $DB->get_in_or_equal($exclude, SQL_PARAMS_NAMED, 'status', false);
        $select = "status $sql AND userid = $userid";
        $DB->delete_records_select('block_courseimport', $select, $params);
    }

    /**
     * Formats a job database record into a form suitable for export.
     *
     * @param \stdClass $record
     * @return array
     */
    protected static function transform_job(\stdClass $record): array {
        return [
            'sourcecourse' => "$record->source : $record->sourcename",
            'targetcourse' => "$record->target : $record->targetname",
            'backupid' => $record->backupid,
            'status' => job::format_status_label($record->status),
            'timecreated' => transform::datetime($record->timecreated),
            'timemodified' => transform::datetime($record->timemodified),
        ];
    }
}
