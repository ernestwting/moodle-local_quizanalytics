<?php
/**
 * English language strings for local_quizanalytics.
 *
 * @package local_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

// pluginname is what shows up as the "Analytics" tab label in the course's
// secondary navigation bar (Course | Settings | Participants | Grades |
// Reports | More), and in Site Administration > Plugins > Local plugins.
$string['pluginname'] = 'Analytics';

$string['quizanalytics:view'] = 'View course-level interactive quiz analytics';

$string['pagetitle']            = 'Quiz analytics';
$string['coursewideheading']    = 'Course-wide analytics';
$string['quizselectlabel']      = 'View a single quiz\'s analytics';
$string['quizselectoption']     = 'All STACK quizzes (course-wide view)';
$string['gobutton']             = 'View';

$string['gradetypelabel']       = 'Compare attempts against:';
$string['gradetypehighest']     = 'Highest Grade';
$string['gradetypeaverage']     = 'Average Grade';
$string['gradetypeminimum']     = 'Minimum Grade';

$string['nostackquizzes']       = 'This course has no STACK quizzes yet, or none have finished attempts.';
$string['noattempts']           = 'No finished attempts yet for this quiz. Analytics will appear once at least one student has completed it.';
$string['nocourseattempts']     = 'None of this course\'s STACK quizzes have finished attempts yet.';
$string['servererror']          = 'The analytics service could not be reached. Contact your Moodle administrator.';
$string['loaderror']            = 'The analytics service returned an unexpected response.';
$string['missingquizreportplugin'] = 'The quiz_quizanalytics report subplugin (mod/quiz/report/quizanalytics) is required by this plugin but is not installed or was removed after install.';

$string['apibaseurl']       = 'Analytics service base URL';
$string['apibaseurl_desc']  = 'Internal base URL of the analytics microservice — the same service quiz_quizanalytics already talks to. This plugin appends /analyze and /analyze-course itself, so enter just the base, e.g. http://127.0.0.1:8600. This should always point at localhost or a private/internal network address — never a public URL — since student response data is POSTed here in full.';
$string['apitimeout']       = 'Analytics service timeout (seconds)';
$string['apitimeout_desc']  = 'How long to wait for the analytics service before giving up. Course-wide requests bundle every STACK quiz\'s attempts in one call, so this may need to be higher than the per-quiz report\'s timeout on courses with many quizzes.';
