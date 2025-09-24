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
 * Attendees ajax updater
 *
 * @package    mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/attendees/lib.php');
require_once($CFG->libdir.'/completionlib.php');

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID.
$tab     = optional_param('tab', null, PARAM_ALPHANUM);
$group   = optional_param('group', null, PARAM_INT);
$view       = optional_param('view', null, PARAM_ALPHANUM);
$location   = optional_param('location', 0, PARAM_INT); // Location filter.

if (!$cm = get_coursemodule_from_id('attendees', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}

$attendees = $DB->get_record('attendees', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

$attendees->view = $view;
$attendees->location = $location;
$attendees->group = $group;
$attendees->tab = !$tab ? $attendees->defaultview : $tab;
$attendees->tab = $attendees->lockview ? $attendees->defaultview : $attendees->tab;

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/attendees:view', $context);

$json = json_encode([]);

$attendees->group = $group;
$attendees->tab = $tab;

// Refresh list of users.
$data = [attendees_get_ui($cm, $attendees, true)];
$json = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

echo $json;
