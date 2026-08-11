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
 * CLI script to bulk sync cohorts to courses.
 *
 * @package    tool_bulkcohortsync
 * @subpackage cli
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/enrol/cohort/lib.php');

$longparams = array(
    'help' => false,
    'courses' => '',
    'cohorts' => '',
    'role' => 'student',
);

$shortparams = array(
    'h' => 'help',
);

list($options, $unrecognized) = cli_get_params($longparams, $shortparams);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || empty($options['courses']) || empty($options['cohorts'])) {
    $help = "Bulk Cohort Sync CLI Tool

Options:
-h, --help            Print out this help
--courses=C1,C2...    Comma-separated list of course shortnames or course IDs.
--cohorts=CO1,CO2...  Comma-separated list of cohort idnumbers, names, or IDs.
--role=student        Role shortname (default: student).

Example:
php admin/tool/bulkcohortsync/cli/sync.php --courses=CS_2026,CS_AI_101 --cohorts=32-27,32-28
";
    cli_writeln($help);
    exit(0);
}

// Resolve role
$roleshortname = $options['role'];
$role = $DB->get_record('role', array('shortname' => $roleshortname));
if (!$role) {
    cli_error("Error: Role '$roleshortname' not found.");
}

// Resolve courses
$courses_input = explode(',', $options['courses']);
$courses = array();
foreach ($courses_input as $ci) {
    $ci = trim($ci);
    if (empty($ci)) continue;
    $c = is_numeric($ci) ? $DB->get_record('course', array('id' => $ci)) : $DB->get_record('course', array('shortname' => $ci));
    if ($c) {
        $courses[] = $c;
    } else {
        cli_writeln("Warning: Course '$ci' not found. Skipping.");
    }
}

// Resolve cohorts
$cohorts_input = explode(',', $options['cohorts']);
$cohorts = array();
foreach ($cohorts_input as $ch) {
    $ch = trim($ch);
    if (empty($ch)) continue;
    if (is_numeric($ch)) {
        $cohort = $DB->get_record('cohort', array('id' => $ch));
    } else {
        $cohort = $DB->get_record('cohort', array('idnumber' => $ch));
        if (!$cohort) {
            $cohort = $DB->get_record('cohort', array('name' => $ch));
        }
    }
    if ($cohort) {
        $cohorts[] = $cohort;
    } else {
        cli_writeln("Warning: Cohort '$ch' not found. Skipping.");
    }
}

if (empty($courses)) cli_error("Error: No valid courses resolved.");
if (empty($cohorts)) cli_error("Error: No valid cohorts resolved.");

$plugin = enrol_get_plugin('cohort');
if (!$plugin) cli_error("Error: Cohort enrollment plugin not found.");

foreach ($courses as $course) {
    foreach ($cohorts as $cohort) {
        $params = array(
            'enrol' => 'cohort',
            'courseid' => $course->id,
            'customint1' => $cohort->id
        );
        if ($DB->record_exists('enrol', $params)) {
            cli_writeln("Info: Cohort '{$cohort->name}' is already synced with course '{$course->shortname}'.");
            continue;
        }

        $fields = array(
            'customint1' => $cohort->id,
            'roleid' => $role->id,
            'status' => ENROL_INSTANCE_ENABLED
        );
        $result = $plugin->add_instance($course, $fields);
        if ($result) {
            cli_writeln("Success: Synced Cohort '{$cohort->name}' (idnumber: {$cohort->idnumber}) to Course '{$course->shortname}'.");
        } else {
            cli_writeln("Error: Failed to sync Cohort '{$cohort->name}' to Course '{$course->shortname}'.");
        }
    }
}

cli_writeln("Bulk sync completed.");
exit(0);
