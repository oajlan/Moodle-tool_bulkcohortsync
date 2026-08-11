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
 * Arabic language strings for tool_bulkcohortsync.
 *
 * @package    tool_bulkcohortsync
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'مزامنة الأفواج بالمقررات بالجملة';
$string['privacy:metadata'] = 'أداة مزامنة الأفواج بالمقررات لا تخزن أي بيانات شخصية.';
$string['description'] = 'حدد عدة زمر وأربطها بعدة مقررات دراسية بنقرة واحدة باستخدام طريقة مزامنة الأفواج.';
$string['selectcourses'] = '1. تحديد المقررات الدراسية';
$string['selectcohorts'] = '2. تحديد الزمر (الأفواج)';
$string['configureandexecute'] = '3. ضبط الإعدادات والتنفيذ';
$string['searchcourses'] = 'بحث عن مقرر دراسي...';
$string['searchcohorts'] = 'بحث عن زمرة...';
$string['selectall'] = 'تحديد الكل';
$string['deselectall'] = 'إلغاء الكل';
$string['selected'] = 'محدد';
$string['selectrole'] = 'اختر الدور المُراد تعيينه:';
$string['syncbtn'] = 'ربط الزمر بالمقررات المختارة';
$string['executionlogs'] = 'سجل التقرير والتنفيذ';
$string['error_selectboth'] = 'يرجى تحديد مقرر واحد على الأقل وزمرة واحدة على الأقل.';
$string['error_noplugin'] = 'تعذر العثور على إضافة مزامنة الأفواج.';
$string['info_alreadysynced'] = 'الزمرة \'{$a->cohort}\' مرتبطة بالفعل بالمقرر \'{$a->course}\'.';
$string['success_synced'] = 'تم بنجاح ربط الزمرة \'{$a->cohort}\' (رمز: {$a->idnumber}) بالمقرر \'{$a->course}\'.';
$string['error_failed'] = 'فشل ربط الزمرة \'{$a->cohort}\' بالمقرر \'{$a->course}\'.';
