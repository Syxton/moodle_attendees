<?php
// This file is part of Moodle - http://moodle.org/
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
 * Attendees restore class file.
 * @package   mod_attendees
 * @category  backup
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_attendees_activity_task
 */

/**
 * Structure step to restore one attendees activity
 */
class restore_attendees_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defining restore struction.
     * @return stdClass     attendees object
     */
    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('attendees', '/activity/attendees');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process backup data from attendees object.
     * @param object $data  data object
     * @return stdClass     attendees object
     */
    protected function process_attendees($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Insert the attendees record.
        $newitemid = $DB->insert_record('attendees', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Add data after backup.
     */
    protected function after_execute() {
        // Add attendees related files, no need to match by itemname.
        $this->add_related_files('mod_attendees', 'intro', null);
        $this->add_related_files('mod_attendees', 'content', null);
    }
}
