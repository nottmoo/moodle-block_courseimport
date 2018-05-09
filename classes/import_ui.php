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
 * Defines class for course block_courseimport_import_ui block
 *
 * @package    block_courseimport
 * @author     Neill Magill
 * @copyright  University of Nottingham
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_courseimport_import_ui extends import_ui {
    public function execute() {
        if ($this->progress >= self::PROGRESS_EXECUTED) {
            throw new backup_ui_exception('backupuialreadyexecuted');
        }
        $this->progress = self::PROGRESS_EXECUTED;
        $this->controller->finish_ui();
        $this->controller->execute_plan();
        $this->stage = new backup_ui_stage_complete($this, $this->stage->get_params(), $this->controller->get_results());
        return true;
    }
}
