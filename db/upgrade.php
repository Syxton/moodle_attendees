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
 * Attendees module upgrade code
 *
 * This file keeps track of upgrades to
 * the resource module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package    mod_attendees
 * @copyright  2023 Matt Davidson
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extra install actions.
 *
 * @param int $oldversion   version number of current block
 */
function xmldb_attendees_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2024041001) {
        // Rename description field to intro, and define field introformat to be added to scheduler.
        $table = new xmldb_table('attendees');
        $iplockfield = new xmldb_field('iplock', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'kioskbuttons');
        if ($dbman->field_exists($table, $iplockfield)) {
            $dbman->rename_field($table, $iplockfield, 'separatelocations', false);
        }

        // Add location field to attendees_timecard.
        $table = new xmldb_table('attendees_timecard');
        $formatfield = new xmldb_field('location', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'ip');

        if (!$dbman->field_exists($table, $formatfield)) {
            $dbman->add_field($table, $formatfield);
        }

        // Create new attendees_locations table.
        $table = new xmldb_table('attendees_locations');

        // Adding fields to table quiz_grade_items.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('aid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, "Default Location");

        // Adding keys to table quiz_grade_items.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table quiz_grade_items.
        $table->add_index('aid', XMLDB_INDEX_NOTUNIQUE, ['aid']);

        // Conditionally launch create table for quiz_grade_items.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Savepoint reached.
        upgrade_mod_savepoint(true, 2024041001, 'attendees');
    }

    if ($oldversion < 2024041005) {
        // Rename description field to intro, and define field introformat to be added to scheduler.
        $table = new xmldb_table('attendees');
        $locationsfield = new xmldb_field('
            multiplelocations',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'kioskbuttons'
        );
        if ($dbman->field_exists($table, $locationsfield)) {
            $dbman->rename_field($table, $locationsfield, 'separatelocations', false);
        }

        // Savepoint reached.
        upgrade_mod_savepoint(true, 2024041005, 'attendees');
    }

    if ($oldversion < 2025092600) {
        // Look at all current attendees blocks and create locations.
        // Then attribute the location to activity of that mod.

        if ($mods = $DB->get_records('attendees')) {
            foreach ($mods as $mod) {
                $locid = false;
                // Check if a location already exists.
                if ($locations = $DB->get_records('attendees_locations', ['aid' => $mod->id])) {
                    foreach ($locations as $loc) {
                        $locid = $loc->id;
                        break;
                    }
                } else {
                    // Create new location.
                    $location = (object) [
                        'name' => 'Sign In / Out Location',
                        'aid' => $mod->id,
                    ];
                    $locid = $DB->insert_record('attendees_locations', $location);
                }

                // Attribute location to all mod activity without a location.
                if ($locid) {
                    $sql = "UPDATE {attendees_timecard} SET location = ? WHERE aid = ? AND location = 0";
                    $DB->execute($sql, [$locid, $mod->id]);
                }
            }
        }

        // Savepoint reached.
        upgrade_mod_savepoint(true, 2025092600, 'attendees');
    }

    return true;
}
