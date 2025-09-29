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
 * Attendees module version information
 *
 * @package    mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/attendees/lib.php');
require_once($CFG->libdir.'/completionlib.php');

// Standard passed variables.
$id         = optional_param('id', 0, PARAM_INT); // Course Module ID.
$group      = optional_param('group', 0, PARAM_INT);
$tab        = optional_param('tab', null, PARAM_ALPHANUM);
$view       = optional_param('view', null, PARAM_ALPHANUM);
$location   = optional_param('location', 0, PARAM_INT); // Location filter.

// Optional passed variables.
$userid     = optional_param('userid', 0, PARAM_INT); // User ID.
$code       = optional_param('code', null, PARAM_RAW);

if (!$cm = get_coursemodule_from_id('attendees', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

// Get the course.
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Check if the user can view the module.
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendees:view', $context);

// Get the attendees record.
$attendees = $DB->get_record('attendees', ['id' => $cm->instance], '*', MUST_EXIST);
$attendees->view = $view;
$attendees->location = $location;
$attendees->group = $group;
$attendees->tab = !$tab ? $attendees->defaultview : $tab;
$attendees->tab = $attendees->lockview && !$attendees->view == "overwatch" ? $attendees->defaultview : $attendees->tab;

// Check if attendees has sign in/out enabled.
$message = "";
if ($attendees->timecard) {
    // Are we signing in a specific user?
    // If not, we are signing in or out ourselves or a person by code lookup.
    if ($userid) {
        require_capability('mod/attendees:signinoutothers', $context);
        $message = attendees_signinout($attendees, $userid);
    } else if (has_capability('mod/attendees:signinout', $context)) {
        // Sign in/out by code in kiosk mode.
        if ($attendees->kioskmode && $code !== null) {
            $return = attendees_lookup($attendees, $code);
            if (!empty($return) && is_numeric($return)) {
                $message = attendees_signinout($attendees, $return);
            } else {
                $message = $return;
            }
        } else { // Attempt to sign yourself in.
            $message = attendees_signinout($attendees, $USER->id);
        }
    }
}

// Completion and trigger events.
attendees_view($attendees, $course, $cm, $context);

$tab = !$tab ? $attendees->defaultview : $tab;
$params = [
    'id' => $id,
    'tab' => $tab,
    'view' => $view,
    'location' => $location,
];
$url = new moodle_url('/mod/attendees/view.php', $params);
redirect($url, $message);
