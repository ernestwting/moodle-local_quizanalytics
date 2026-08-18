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
$string['coursewideheading']    = 'Course-wide analytics';
$string['downloadpdfbutton']  = 'Download PDF';
$string['generatepdfheading'] = 'Generate PDF report';
$string['gobutton']             = 'View';
$string['gradetypeaverage']     = 'Average grade';
$string['gradetypehighest']     = 'Highest grade';
$string['gradetypelabel']       = 'Compare attempts against:';
$string['gradetypeminimum']     = 'Minimum grade';
$string['loaderror']            = 'Analytics returned an unexpected response.';
$string['noattempts']           = 'No finished attempts yet for this quiz. Analytics will appear once at least one student has completed it.';
$string['nocourseattempts']     = 'None of this course\'s STACK quizzes have finished attempts yet.';
$string['nostackquestions']     = 'This quiz has no STACK questions to visualize.';
$string['nostackquizzes']       = 'This course has no STACK quizzes yet, or none have finished attempts.';
$string['pagetitle']            = 'Quiz analytics';
$string['pdfchartunavailable']  = '{$a} — chart image unavailable (not captured from the page).';
$string['pdferror']           = 'The PDF report could not be generated. Contact your Moodle administrator.';
$string['pdfnosections']        = 'No sections were selected for this report.';
$string['pdfquizsubtitle']        = 'Combined across every STACK quiz in the course';
$string['pdfsectionattemptlist']       = '1. Merged List of Users and Files';
$string['pdfsectionboxplot']           = '3. Quiz Grade Distribution (Box Plot)';
$string['pdfsectioncrossattempt']      = 'Cross-Attempt Comparison';
$string['pdfsectiondifficulty']        = '2. Question Difficulty Analysis';
$string['pdfsectionengagement']        = '4. Engagement Over Time';
$string['pdfsectionmetrics']           = '6. Question Metrics';
$string['pdfsectionnetworkfeatures']   = 'Network Features per Node';
$string['pdfsectionprtdistance3d']     = 'PRT-Distance 3D Chart';
$string['pdfsectionquestiondetails']        = '3. Question Item Details & Error Drill-Down';
$string['pdfsectionquestiondetailscaption'] = 'Question text, right answer, and wrong-response drill-down (Best Attempt)';
$string['pdfsectionquizstats']         = '2. Summary of Quiz Stats';
$string['pdfsectionresponsedistribution'] = '4. Question Response Distribution';
$string['pdfsectionscatter']           = '5. Scatter Plot: Attempts vs Grades';
$string['pdfsectionstudentmatrix']     = '5. Student Performance Matrix';
$string['pdfsectionsummary']        = '1. Question Summary';
$string['pdfsectionsummarycaption'] = 'Participation and summary statistics';
$string['pdfsectiontransitiongraph']   = 'Class-Wide Transition Graph';
$string['pdfsectiontreeeditdistance3d'] = 'Tree Edit Distance 3D Chart';
$string['pdfsectiontrend']             = '6. Line Graph of Various Metrics';
$string['pdfsolutionprocesssubtitle'] = '{$a->question}, part {$a->part}';
$string['pdftitlequestion']       = '{$a} — Question Analytics';
$string['pdftitlequiz']           = '{$a} — Quiz Analysis';
$string['pdftitlesolutionprocess'] = '{$a} — Solution Process Visualization';
$string['pdftruncatedrows']       = 'Showing the first {$a->shown} of {$a->total} rows.';
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
$string['viewquestionanalytics'] = 'Question analytics';
$string['viewselectlabel']      = 'View:';
$string['viewsolutionprocess']  = 'Solution process visualization';
