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
 * Settings for the courseimport block.
 *
 * Import include defaults are configured in core General import settings
 * (Site administration → Courses → Backups → General import defaults), not here.
 *
 * @package    block_courseimport
 * @copyright  2026 University of Nottingham
 * @author     Nisha Sarala <nisha.sarala@nottingham.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\url;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig && !$ADMIN->locate('block_courseimport_bulkrollover')) {
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_courseimport_bulkrollover',
        get_string('bulkrollover', 'block_courseimport'),
        new url('/blocks/courseimport/bulk/index.php'),
        'block/courseimport:manage'
    ));
}
