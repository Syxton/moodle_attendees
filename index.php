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

require('../../config.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course, true);
$PAGE->set_pagelayout('incourse');

// Trigger instances list viewed event.
$event = \mod_attendees\event\course_module_instance_list_viewed::create(['context' => context_course::instance($course->id)]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strpage   = get_string('modulename', 'attendees');
$strname   = get_string('name');
$strintro  = get_string('moduleintro');

$PAGE->set_url('/mod/attendees/index.php', ['id' => $course->id]);
$PAGE->set_title($course->shortname . ': '. $strpage);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strpage);
echo $OUTPUT->header();
echo $OUTPUT->heading($strpage);

if (!$pages = get_all_instances_in_course('attendees', $course)) {
    $url = new moodle_url('/course/view.php', ['id' => $course->id]);
    notice(get_string('thereareno', 'moodle', $strpage), $url);
    exit;
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $table->head  = [$strsectionname, $strname, $strintro];
    $table->align = ['center', 'left', 'left'];
} else {
    $table->head  = [$strname, $strintro];
    $table->align = ['left', 'left'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($pages as $attendees) {
    $cm = $modinfo->cms[$attendees->coursemodule];
    if ($usesections) {
        $printsection = '';
        if ($attendees->section !== $currentsection) {
            if ($attendees->section) {
                $printsection = get_section_name($course, $attendees->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $attendees->section;
        }
    }

    $class = $attendees->visible ? '' : 'class="dimmed"'; // Hidden modules are dimmed.

    $params = ['id' => $cm->id, 'view' => "menu"];
    $url = new moodle_url('/mod/attendees/view.php', $params);

    $table->data[] = [
        $printsection,
        "<a $class href=\"$url\">" .
            format_string($attendees->name) .
        "</a>",
        format_module_intro('attendees', $attendees, $cm->id),
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
