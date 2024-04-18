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
 * List of all pages in course
 *
 * @package mod_attendees
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendees\form;

/**
 * Form to search through attendees history.
 *
 * @package mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class historyform extends \moodleform {
    /**
     * Add elements to form.
     */
    public function definition() {
        $cm = $this->_customdata['cm']; // The course module object.

        // A reference to the form is stored in $this->form.
        // A common convention is to store it in a variable, such as `$mform`.
        $mform = $this->_form; // Don't forget the underscore!

        // A hidden field to store the course module id.
        $mform->addElement('hidden', 'id', $cm->id);
        $mform->setType('id', PARAM_INT); // The data type of the element.

        // A hidden field to indicate the form is being used for searching history.
        $mform->addElement('hidden', 'h', 'true');
        $mform->setType('h', PARAM_BOOL); // The data type of the element.

        // A hidden field to store the page number.
        $mform->addElement('hidden', 'h_page', 0);
        $mform->setType('h_page', PARAM_INT); // The data type of the element.

        // From date/time.
        $mform->addElement('date_selector', 'h_from', get_string('from'), ['optional' => true]);
        $mform->setType('h_from', PARAM_INT); // The data type of the element.

        // To date/time.
        $mform->addElement('date_selector', 'h_to', get_string('to'), ['optional' => true]);
        $mform->setType('h_to', PARAM_INT); // The data type of the element.

        // Autocomplete field for selecting users.
        $users = [];
        if ($allusers = $this->get_all_users($cm)) {
            foreach ($allusers as $user) {
                $users[$user->id] = $user->firstname . ' ' . $user->lastname;
            }
        }
        $options = [
            'multiple' => true, // Allow multiple selections.
            'noselectionstring' => get_string('allusers', 'search'), // String to display when no user is selected.
        ];
        $mform->addElement('autocomplete', 'h_user', get_string('users'), $users, $options);

        // Course enrollment filter.
        $options = [
            'multiple' => true,
            'limittoenrolled' => false,
            'noselectionstring' => get_string('allcourses', 'search'),
        ];
        $mform->addElement('course', 'h_courses', get_string('enrolledin', 'attendees'), $options);
        $mform->setType('courseids', PARAM_INT);

        // Autocomplete field for selecting locations.
        $locations = [];
        if ($alllocations = $this->get_all_locations($cm)) {
            foreach ($alllocations as $l) {
                $locations[$l->ip] = $l->ip;
            }
        }
        $options = [
            'multiple' => true, // Allow multiple selections.
            'noselectionstring' => get_string('all', 'search'), // String to display when no location is selected.
        ];
        $mform->addElement('autocomplete', 'h_locations', get_string('locations', 'attendees'), $locations, $options);

        // Submit button.
        $mform->addElement('submit', 'filterattendees', get_string('filter'));
    }

    /**
     * Get all users who have attended the activity.
     *
     * @param stdClass $cm The course module record.
     * @return array The recordset of user records.
     */
    private function get_all_users($cm) {
        global $DB;

        // User Search.
        $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
                  FROM {attendees_timecard} t
                  JOIN {user} u ON u.id = t.userid
                 WHERE t.aid = :id";

        return $DB->get_records_sql($sql, ['id' => $cm->instance]);
    }

    /**
     * Get all unique locations for the activity.
     *
     * @param stdClass $cm The course module record.
     * @return array The recordset of location records.
     */
    private function get_all_locations($cm) {
        global $DB;

        // User Search.
        $sql = "SELECT DISTINCT t.ip
                  FROM {attendees_timecard} t
                 WHERE t.aid = :id";

        return $DB->get_records_sql($sql, ['id' => $cm->instance]);
    }
}
