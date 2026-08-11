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
 * English language strings for tool_bulkcohortsync.
 *
 * @package    tool_bulkcohortsync
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Bulk Cohort Course Sync';
$string['privacy:metadata'] = 'The Bulk Cohort Course Sync plugin does not store any personal data.';
$string['description'] = 'Select multiple cohorts and link them to multiple courses instantly using Moodle\'s cohort synchronization method.';
$string['selectcourses'] = '1. Select Courses';
$string['selectcohorts'] = '2. Select Cohorts';
$string['configureandexecute'] = '3. Configure & Execute';
$string['searchcourses'] = 'Search courses...';
$string['searchcohorts'] = 'Search cohorts...';
$string['selectall'] = 'Select All';
$string['deselectall'] = 'Deselect All';
$string['selected'] = 'Selected';
$string['selectrole'] = 'Select Role to Assign:';
$string['syncbtn'] = 'Sync Cohorts to Courses';
$string['executionlogs'] = 'Execution Logs';
$string['error_selectboth'] = 'Please select at least one course and one cohort.';
$string['error_noplugin'] = 'Cohort enrollment plugin not found.';
$string['info_alreadysynced'] = 'Cohort \'{$a->cohort}\' is already synced with course \'{$a->course}\'.';
$string['success_synced'] = 'Successfully synced Cohort \'{$a->cohort}\' (idnumber: {$a->idnumber}) to Course \'{$a->course}\'.';
$string['error_failed'] = 'Failed to sync Cohort \'{$a->cohort}\' to Course \'{$a->course}\'.';
