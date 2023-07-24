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
 * Attendees module admin settings and defaults
 *
 * @package mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $settings->add(new admin_setting_configcheckbox('attendees/timecard',
        get_string('timecard', 'attendees'), get_string('timecardexplain', 'attendees'), 0));
    $settings->add(new admin_setting_configcheckbox('attendees/autosignout',
        get_string('autosignout', 'attendees'), get_string('autosignoutexplain', 'attendees'), 1));
    $settings->add(new admin_setting_configcheckbox('attendees/showroster',
        get_string('showroster', 'attendees'), get_string('showrosterexplain', 'attendees'), 0));
    $settings->add(new admin_setting_configcheckbox('attendees/showgroups',
        get_string('showgroups', 'attendees'), get_string('showgroupsexplain', 'attendees'), 1));
    $settings->add(new admin_setting_configcheckbox('attendees/lockview',
        get_string('lockview', 'attendees'), get_string('lockviewexplain', 'attendees'), 0));
    $settings->add(new admin_setting_configcheckbox('attendees/kioskmode',
        get_string('kioskmode', 'attendees'), get_string('kioskmodeexplain', 'attendees'), 0));
    $settings->add(new admin_setting_configcheckbox('attendees/iplock',
        get_string('iplock', 'attendees'), get_string('iplockexplain', 'attendees'), 0));
}
