<?php
// This file is part of courseimport block in Moodle
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
//

/**
 * Displays the form for the block's admin settings.
 *
 * @package    block_courseimport
 * @author     Yijun Xue
 * @copyright  University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configselect('block_courseimport/filesize',
                get_string('max_file_size', 'block_courseimport'),
                " ",
                1,
                array(1 => 1, 3 => 3, 5 => 5, 7 => 7, 9 => 9, 10 => 10, 15 => 15, 20 => 20)));
        $infotime = get_string('infotime', 'block_courseimport');
        $settings->add(new admin_setting_configtext('block_courseimport/crontime',
                get_string('time', 'block_courseimport'),
                $infotime,
                null));
    }
}
