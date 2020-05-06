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
 * Helper for the import page.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_courseimport;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/util/settings/base_setting.class.php');

/**
 * Helper for the import page.
 *
 * @package    block_courseimport
 * @author     Neill Magill <neill.magill@nottingham.ac.uk>
 * @copyright  2020 University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_helper {
    /**
     * Stops the user from changing the setting of the user import setting.
     *
     * @param \backup_plan $plan
     */
    public static function disbable_userdata_import(\backup_plan $plan) {
        $usersetting = $plan->get_setting('users');
        $usersetting->set_status(\backup_setting::LOCKED_BY_CONFIG);
    }

    /**
     * Hides locked settings from the user.
     *
     * @param \backup_plan $plan
     * @return bool True if there are any visible settings, otherwise false.
     */
    public static function hide_locked_settings(\backup_plan $plan): bool {
        $skip = false;
        $settings = $plan->get_settings();
        foreach ($settings as $setting) {
            if ($setting->get_status() !== \backup_setting::NOT_LOCKED) {
                $setting->set_visibility(\backup_setting::HIDDEN);
            } else {
                $skip = true;
            }
        }
        return $skip;
    }

    /**
     * Disables the import of selected activities.
     *
     * @param \base_task $task
     * @return void
     */
    public static function filter_task(\base_task $task) {
        foreach ($task->get_settings() as $setting) {
            $taskname = $task->get_name();
            $settingname = $setting->get_name();

            // We will not import Turnitin activities.
            if(preg_match('/^turnitintool(two)?_[0-9]+_[a-z]+/', $settingname) === 1) {
                $label = "<b>$taskname</b>";
                static::disable_setting($setting, $label);
                // We are done here.
                return;
            }

            // We want to check resource activities.
            $isresource = (strpos($settingname, 'resource_') !== false);
            $isincluded = (strpos($settingname, '_included') !== false);

            if ($isresource && $isincluded) {
                $resourceid = str_replace("_included", "", str_replace("resource_", "", $settingname));
                $file = self::get_resource_filesize($resourceid);
                if (!is_null($file) && strpos($file->type, 'video') !== false) {
                    // Do not process video files.
                    $warning = get_string('videofile', 'block_courseimport');
                    $label = "$taskname <b><u>$warning</u></b>";
                    static::disable_setting($setting, $label);
                }
                // We are done.
                return;
            }
        }
    }

    /**
     * Finds the size of the first file in a resource.
     *
     * @param int $id The course module id for a resource.
     * @return \fileinfo|null
     */
    public static function get_resource_filesize(int $id): ?\stdClass {
        $context = \context_module::instance($id);
        $fs = get_file_storage();
        // Get only the first record.
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false, 0, 0, 1);

        if (count($files) < 1) {
            return null;
        }

        // Get the file.
        $file = reset($files);

        $fileinfo = new \stdClass();
        $fileinfo->size = $file->get_filesize();
        $fileinfo->type = $file->get_mimetype();
        return $fileinfo;
    }

    /**
     * Disabled a setting in the backup options.
     *
     * @param \base_setting $setting The setting to be disabled.
     * @param string $label The new label for the setting.
     */
    protected static function disable_setting(\base_setting $setting, string $label) {
        $setting->set_value('0');
        $setting->make_ui(
                \base_setting::UI_HTML_CHECKBOX,
                $label,
                ['disabled' => true],
                null
        );
        $setting->set_status(\base_setting::LOCKED_BY_HIERARCHY);
    }
}