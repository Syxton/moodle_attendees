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
 * Attendees backup class file.
 * @package   mod_attendees
 * @category  backup
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the backup steps that will be used by the backup_attendees_activity_task
 */

/**
 * Define the complete attendees structure for backup, with file and id annotations
 */
class backup_attendees_activity_structure_step extends backup_activity_structure_step {
    /**
     * Defining backup struction.
     * @return stdClass     attendees object
     */
    protected function define_structure() {
        // Define each element separated.
        $attendees = new backup_nested_element('attendees', ['id'], ['name',
                                                                     'intro',
                                                                     'introformat',
                                                                     'timecard',
                                                                     'autosignout',
                                                                     'defaultview',
                                                                     'lockview',
                                                                     'showroster',
                                                                     'kioskmode',
                                                                     'kioskbuttons',
                                                                     'iplock',
                                                                     'searchfields',
                                                                     'showgroups',
                                                                    ]);

        // Define sources.
        $attendees->set_source_table('attendees', ['id' => backup::VAR_ACTIVITYID]);

        // Define file annotations.
        $attendees->annotate_files('mod_attendees', 'intro', null);

        // Return the root element (attendees), wrapped into standard activity structure.
        return $this->prepare_activity_structure($attendees);
    }
}
