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
 * English language strings for local_quizanalytics.
 *
 * @package local_quizanalytics
 * @copyright  2026 Ernest Ting <eting@caltech.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Pluginname is what shows up as the "Analytics" tab label in the course's
// secondary navigation bar (Course | Settings | Participants | Grades |
// Reports | More), as the link this plugin adds to each STACK quiz's own
// settings/administration menu, and in Site Administration > Plugins >
// Local plugins.
$string['anonymizemode'] = 'Anonymize student data';
$string['colorblindmode'] = 'Colorblind mode';
$string['computetimelimit']      = 'Computation time limit (seconds)';
$string['computetimelimit_desc'] = 'Raises PHP\'s own execution time limit before the heaviest analytics computations (course-wide analysis, and any PDF export) — these run in-process rather than calling a separate service, so a course with many STACK quizzes/students may need longer than PHP\'s normal max_execution_time allows. 0 leaves PHP\'s own default in place.';
$string['coursewideheading']    = 'Course-Wide Analytics';
$string['downloadpdfbutton']  = 'Download PDF';
$string['generatepdfheading'] = 'Generate PDF Report';
$string['gobutton']             = 'View';
$string['gradetypeaverage']     = 'Average Grade';
$string['gradetypehighest']     = 'Highest Grade';
$string['gradetypelabel']       = 'Compare attempts against:';
$string['gradetypeminimum']     = 'Minimum Grade';
$string['loaderror']            = 'Analytics returned an unexpected response.';
$string['noattempts']           = 'No finished attempts yet for this quiz. Analytics will appear once at least one student has completed it.';
$string['nocourseattempts']     = 'None of this course\'s STACK quizzes have finished attempts yet.';
$string['nostackquestions']     = 'This quiz has no STACK questions to visualize.';
$string['nostackquizzes']       = 'This course has no STACK quizzes yet, or none have finished attempts.';
$string['pagetitle']            = 'Quiz analytics';
$string['pdferror']           = 'The PDF report could not be generated. Contact your Moodle administrator.';
$string['pluginname'] = 'Analytics';

$string['privacy:metadata'] = 'The Quiz Analytics plugin does not store any personal data of its own. It reads finished quiz attempts, question responses, and user records directly from Moodle\'s own database (mod_quiz, the question engine, and core_user) at request time, all of which are already covered by their own privacy providers. Any cached results (Moodle\'s own MUC cache API) are a purely derived, disposable recomputation of that same data, automatically invalidated whenever the underlying attempts change.';
$string['quizanalytics:view'] = 'View STACK quiz analytics';

$string['quizselectlabel']      = 'View a single quiz\'s analytics';
$string['quizselectoption']     = 'All STACK quizzes (course-wide view)';

$string['selectpart']           = 'Part';
$string['selectquestion']       = 'Question';
$string['selectstudent']        = 'Student drill-down';
$string['selectstudentnone']    = 'None';
$string['servererror']          = 'Analytics could not be computed for this quiz. Contact your Moodle administrator.';
$string['viewquestionanalytics'] = 'Question Analytics';
$string['viewselectlabel']      = 'View:';
$string['viewsolutionprocess']  = 'Solution Process Visualization';
