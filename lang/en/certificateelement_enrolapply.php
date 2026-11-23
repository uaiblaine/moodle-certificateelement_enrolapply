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
 * Strings for the enrolment application answer element.
 *
 * @package    certificateelement_enrolapply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['knownshortnames'] = 'Short names in use on this site: {$a}';
$string['pluginname'] = 'Enrolment application answer';
$string['privacy:metadata:issues'] = 'The certificate tool\'s table of issued certificates, in whose data the enrolment application answer element stores a copy of the answers the certificate prints.';
$string['privacy:metadata:issues:data'] = 'The answers to the enrolment application questions that the certificate prints, as they were when it was issued.';
$string['privacy:metadata:issues:userid'] = 'The user the certificate was issued to.';
$string['shortname'] = 'Question short name';
$string['shortname_help'] = 'The short name of the application question whose answer is printed, as set in the question\'s own settings on the enrolment method.

* The answer comes from the learner\'s approved application in the course the certificate is issued for.
* Questions of different courses answer to the same element when they have the same short name; a shared question has one short name for the whole site.
* The answer is printed as the application recorded it: "Yes" or "No" for a checkbox, the chosen option for a menu, the date in full for a date.
* The answer is fixed when the certificate is issued.

**Example**: "unit" prints "3rd Battalion" for a learner who gave that answer to the question whose short name is "unit".

The printed answer is read by everybody who can see the certificate or verify it with its code, not only by whoever decides applications, so name only questions meant for a certificate.

Nothing is printed for a learner who was enrolled another way or left the question unanswered.';
$string['shortnameinvalid'] = 'A short name has lower case letters, numbers and underscores only, 100 characters at most.';
