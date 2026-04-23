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
 * Shared import profile defaults used by settings/install/upgrade.
 * 
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!function_exists('block_courseimport_profile_toggle_defaults')) {
    /**
     * Shared import profile defaults used by settings/install/upgrade.
     *
     * @return array<string, string>
     */
    function block_courseimport_profile_toggle_defaults(): array {
        return [
            'includepermissionoverrides' => '0',
            'includeactivitiesresources' => '1',
            'includeblocks' => '1',
            'includefiles' => '0',
            'includefilters' => '1',
            'includecalendarevents' => '0',
            'includequestionbank' => '1',
            'includegroupsgroupings' => '0',
            'includecustomfields' => '1',
            'includecontentbankcontent' => '1',
            'includelegacycoursefiles' => '0',
        ];
    }
}

