<?php
/**
 * English language strings for local_quizanalytics.
 *
 * @package local_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

// pluginname is what shows up as the "Analytics" tab label in the course's
// secondary navigation bar (Course | Settings | Participants | Grades |
// Reports | More), as the link this plugin adds to each STACK quiz's own
// settings/administration menu, and in Site Administration > Plugins >
// Local plugins.
$string['pluginname'] = 'Analytics';

$string['quizanalytics:view'] = 'View STACK quiz analytics';

$string['pagetitle']            = 'Quiz analytics';
$string['coursewideheading']    = 'Course-Wide Analytics';
$string['quizselectlabel']      = 'View a single quiz\'s analytics';
$string['quizselectoption']     = 'All STACK quizzes (course-wide view)';
$string['gobutton']             = 'View';

$string['viewselectlabel']      = 'View:';
$string['viewquestionanalytics'] = 'Question Analytics';
$string['viewsolutionprocess']  = 'Solution Process Visualization';

$string['gradetypelabel']       = 'Compare attempts against:';
$string['gradetypehighest']     = 'Highest Grade';
$string['gradetypeaverage']     = 'Average Grade';
$string['gradetypeminimum']     = 'Minimum Grade';

$string['selectquestion']       = 'Question';
$string['selectpart']           = 'Part';
$string['selectstudent']        = 'Student drill-down';
$string['selectstudentnone']    = 'None';
$string['nostackquestions']     = 'This quiz has no STACK questions to visualize.';

$string['nostackquizzes']       = 'This course has no STACK quizzes yet, or none have finished attempts.';
$string['noattempts']           = 'No finished attempts yet for this quiz. Analytics will appear once at least one student has completed it.';
$string['nocourseattempts']     = 'None of this course\'s STACK quizzes have finished attempts yet.';
$string['servererror']          = 'Analytics could not be computed for this quiz. Contact your Moodle administrator.';
$string['loaderror']            = 'Analytics returned an unexpected response.';

$string['computetimelimit']      = 'Computation time limit (seconds)';
$string['computetimelimit_desc'] = 'Raises PHP\'s own execution time limit before the heaviest analytics computations (course-wide analysis, and any PDF export) — these run in-process rather than calling a separate service, so a course with many STACK quizzes/students may need longer than PHP\'s normal max_execution_time allows. 0 leaves PHP\'s own default in place.';

$string['colorblindmode'] = 'Colorblind mode';

$string['generatepdfheading'] = 'Generate PDF Report';
$string['downloadpdfbutton']  = 'Download PDF';
$string['pdferror']           = 'The PDF report could not be generated. Contact your Moodle administrator.';
