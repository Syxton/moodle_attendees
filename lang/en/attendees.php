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
 * Strings for component 'attendees', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package    mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['codenotfound'] = 'No User found';
$string['configdisplayoptions'] = 'Select all options that should be available, existing settings are not modified. Hold CTRL key to select multiple fields.';
$string['content'] = 'Attendees content';
$string['contentheader'] = 'Content';
$string['displayoptions'] = 'Available display options';
$string['displayselect'] = 'Display';
$string['displayselectexplain'] = 'Select display type.';


$string['modulename'] = 'Attendees';
$string['modulename_help'] = 'The attendees module enables a teacher to view all students by profile image and/or maintain an active / inactive student roster based on daily sign in/out times.';
$string['modulename_link'] = 'mod/attendees/view';
$string['modulenameplural'] = 'Attendeess';
$string['onlyin'] = 'Only signed in';
$string['onlyout'] = 'Only signed out';
$string['optionsheader'] = 'Display options';
$string['signin'] = 'Sign In';
$string['signout'] = 'Sign Out';
$string['signinout'] = 'Sign in / Out';
$string['usersearch'] = 'User Search';

$string['attendees-mod-attendees-x'] = 'Any attendees module attendees';
$string['attendees:addinstance'] = 'Add a new attendees resource';
$string['attendees:signinout'] = 'Can sign in/out';
$string['attendees:signinoutothers'] = 'Can change in/out status of enrolled users';
$string['attendees:view'] = 'View attendees area';
$string['attendees:viewrosters'] = 'View rosters in attendees module';
$string['messagesignedin'] = '{$a->firstname} {$a->lastname} has been signed in.';
$string['messagesignedout'] = '{$a->firstname} {$a->lastname} has been signed out.';
$string['pluginadministration'] = 'Attendees module administration';
$string['pluginname'] = 'Attendees';
$string['privacy:metadata'] = 'The Attendees resource plugin does not store any personal data.';
$string['search:activity'] = 'Attendees';

// Config variables.
$string['autosignout'] = 'Automatically sign out students at the end of the day';
$string['autosignoutexplain'] = 'Students that are signed in will be signed out at the end of the day.';
$string['iplock'] = 'IP Dependant Lock';
$string['iplockexplain'] = 'Only show students as active if they have signed in through current device.';
$string['kioskmode'] = 'Kiosk mode';
$string['kioskmodeexplain'] = 'Reduced "Moodle stuff" like headers.  A session keep alive so that timeout does not occur';
$string['kioskbuttons'] = 'Kiosk mode sign in/out buttons.';
$string['kioskbuttonsexplain'] = 'In Kiosk Mode, show sign in or sign out buttons.';
$string['lockview'] = 'Do not allow other roster views. (Locked view)';
$string['lockviewexplain'] = 'Show default roster view. Do not show tabs for All / Active Users / Inactive Users';
$string['passwordprotected'] = 'Require password to sign in';
$string['rosterview'] = 'Select default roster view.';
$string['searchfields'] = 'Search Fields';
$string['searchfieldsexplain'] = 'User fields that will be searched to sign in/out in kiosk mode.';
$string['showgroups'] = 'Show user groups';
$string['showgroupsexplain'] = 'Show the groups that each user is a member of.';
$string['showroster'] = 'Show roster to students';
$string['showrosterexplain'] = 'Students should be able to see All / Only In / Only Out student rosters';
$string['timecard'] = 'Timecard';
$string['timecardenabled'] = 'Enable timecard';
$string['timecardexplain'] = 'Allow enrolled students to daily sign in/out';
