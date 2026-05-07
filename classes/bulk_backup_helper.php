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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_plan_builder.class.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/ui/import_extensions.php');

/**
 * Build import-mode backup controllers without the interactive UI (cron will execute_plan).
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_backup_helper {
    /**
     * Creates a non-interactive import-mode backup for the source course and persists controller state.
     *
     * @param int $sourcecourseid Course id to export from.
     * @param int $userid User owning the backup controller (typically the submitting user).
     * @return string backup id ({@see \backup_controller::get_backupid()}).
     */
    public static function create_backup_controller(int $sourcecourseid, int $userid): string {
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $sourcecourseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $userid
        );
        $plan = $bc->get_plan();
        import_helper::disbable_userdata_import($plan);
        import_helper::apply_plan_setting_toggles($plan);
        import_helper::hide_locked_settings($plan);
        foreach ($bc->get_plan()->get_tasks() as $task) {
            import_helper::filter_task($task);
        }
        // Persist plan tweaks (matches interactive import after UI changes); otherwise checksum/state may not match cron load.
        $bc->save_controller();
        $backupid = $bc->get_backupid();
        $bc->destroy();
        return $backupid;
    }
}
