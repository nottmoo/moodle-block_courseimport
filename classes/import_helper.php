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

use backup_setting;
use base_setting;

defined('MOODLE_INTERNAL') || die();

// Load backup core first (defines backup_exception) before settings classes extend it.
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/moodle2/backup_settingslib.php');
require_once($CFG->dirroot . '/blocks/courseimport/db/profiledefaults.php');
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
     * Default checkbox values for the import profile (admin settings and bulk UI summary).
     *
     * @return array<string, bool>
     */
    public static function profile_toggle_defaults(): array {
        $defaults = [];
        foreach (block_courseimport_profile_toggle_defaults() as $key => $value) {
            $defaults[$key] = ((int)$value) === 1;
        }
        return $defaults;
    }

    /**
     * Whether a profile toggle is enabled (same interpretation as backup/import apply logic).
     *
     * @param string $key
     * @return bool
     */
    public static function profile_toggle_enabled(string $key): bool {
        $defaults = self::profile_toggle_defaults();
        $default = $defaults[$key] ?? false;
        return self::config_enabled($key, $default);
    }

    /**
     * Human-readable labels for enabled profile options (admin order), for bulk upload sidebar.
     *
     * @return string[] HTML-safe plain text lines
     */
    public static function enabled_profile_sidebar_labels(): array {
        $keys = array_keys(self::profile_toggle_defaults());
        $out = [];
        foreach ($keys as $key) {
            if (self::profile_toggle_enabled($key)) {
                $out[] = get_string($key, 'block_courseimport');
            }
        }
        return $out;
    }

    /**
     * Gets a boolean plugin config value.
     *
     * @param string $key
     * @param bool $default
     * @return bool
     */
    public static function config_enabled(string $key, bool $default = true): bool {
        $value = get_config('block_courseimport', $key);
        if ($value === false) {
            return $default;
        }
        return ((int)$value) === 1;
    }

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
     * Applies include/exclude profile toggles from plugin settings.
     *
     * @param \backup_plan $plan
     * @return void
     */
    public static function apply_plan_setting_toggles(\backup_plan $plan): void {
        $toggles = [
            'includepermissionoverrides' => ['role_assignments'],
            'includeblocks' => ['blocks'],
            'includefiles' => ['files'],
            'includefilters' => ['filters'],
            'includecalendarevents' => ['calendarevents'],
            'includequestionbank' => ['questionbank'],
            'includegroupsgroupings' => ['groups', 'groupings'],
            'includecustomfields' => ['customfields'],
            'includecontentbankcontent' => ['contentbankcontent'],
            'includelegacycoursefiles' => ['legacyfiles'],
        ];
        foreach ($toggles as $configkey => $settingnames) {
            if (self::profile_toggle_enabled($configkey)) {
                continue;
            }
            foreach ($settingnames as $settingname) {
                try {
                    $setting = $plan->get_setting($settingname);
                    if ($setting instanceof \base_setting) {
                        self::disable_setting($setting);
                    }
                } catch (\Throwable $e) {
                    // Missing setting in a specific backup plan, ignore safely.
                }
            }
        }
    }

    /**
     * Unselect announcement activity
     *
     * @param base_setting $setting An announcement activity's settings
     * @param string $instanceid Id of a forum
     * @return void
     */
    public static function unselect_announcement(\base_setting $setting, string $instanceid) {
        global $DB;
        // Check if the forum is an announcement, which type is news.
        $params = ['instanceid' => $instanceid, 'type' => "news"];
        $sql = 'SELECT *
                  FROM {forum} fo
                  JOIN {course_modules} cm
                    ON (fo.id = cm.instance)
                 WHERE (cm.id = :instanceid) AND (fo.type = :type)';
        if ($DB->record_exists_sql($sql, $params)) {
            if ($setting->get_ui_type() == backup_setting::UI_HTML_CHECKBOX) {
                $originalsetting = $setting->get_status();
                $setting->set_status(\base_setting::NOT_LOCKED);
                $setting->set_value(0);
                $setting->set_status($originalsetting);
            }
        }
    }

    /**
     * Unselect moodle activity for import
     *
     * @param base_setting $setting An activity's setting
     * @return void
     */
    public static function unselect_activity(\base_setting $setting) {
        if ($setting->get_ui_type() == backup_setting::UI_HTML_CHECKBOX) {
            $originalsetting = $setting->get_status();
            $setting->set_status(\base_setting::NOT_LOCKED);
            $setting->set_value(0);
            $setting->set_status($originalsetting);
        }
    }


    /**
     * Disables the import of selected activities.
     *
     * @param \base_task $task
     * @return void
     */
    public static function filter_task(\base_task $task) {
        $includeactivities = self::profile_toggle_enabled('includeactivitiesresources');
        foreach ($task->get_settings() as $setting) {
            $settingname = $setting->get_name();

            if (!$includeactivities && preg_match('/_[0-9]+_included$|_included$/', $settingname) === 1) {
                self::disable_setting($setting);
                continue;
            }

            // Unselect announcement forum activities.
            if (preg_match('/^(forum_)\K[0-9]+(?=_included)/', $settingname, $instanceid) === 1) {
                self::unselect_announcement($setting, $instanceid[0]);
            }

            // Unselect moodle assignment and  tutorialbooking activities.
            if (preg_match('/^((assign|tutorialbooking)_)\K[0-9]+(?=_included)/', $settingname) === 1) {
                self::unselect_activity($setting);
            }

            // We will not import Turnitin activities.
            if (preg_match('/^turnitintool(two)?_[0-9]+_[a-z]+/', $settingname) === 1) {
                static::disable_setting($setting);
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
                    static::disable_setting($setting);
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
     */
    protected static function disable_setting(\base_setting $setting) {
        if ($setting->get_value() !== false) {
            $setting->set_status(\base_setting::NOT_LOCKED);
            $setting->set_value(false);
        }
        $setting->set_status(\base_setting::LOCKED_BY_CONFIG);
    }
}
