<?php
/**
 * English language strings for quiz_solutionprocess.
 *
 * @package quiz_solutionprocess
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Solution Process Visualization';

// Required by mod/quiz/lib.php's report tab/dropdown builder, which calls
// get_string($report, 'quiz_' . $report) — i.e. a BARE key matching the
// report's folder name exactly, no suffix. This is a different convention
// from solutionprocessreport below; both are needed (see
// quiz_quizanalytics's lang file, where this exact gotcha was first hit).
$string['solutionprocess'] = 'Solution Process Visualization';

// Required by mod/quiz/settings.php, which builds the "Quiz reports" admin
// list via get_string($report . 'report', 'quiz_' . $report).
$string['solutionprocessreport'] = 'Solution Process Visualization report';

$string['solutionprocess:view'] = 'View solution process visualizations';

$string['noattempts']  = 'No finished attempts yet. Visualizations will appear once at least one student has completed the quiz.';
$string['servererror'] = 'The analytics service could not be reached. Contact your Moodle administrator.';
$string['nostackquestions'] = 'This quiz has no STACK questions to visualize.';

$string['apibaseurl']      = 'Analytics service base URL';
$string['apibaseurl_desc'] = 'Internal base URL of the analytics microservice (no trailing path — /solution-process/meta and /solution-process are appended automatically). This should always point at localhost or a private/internal network address — never a public URL — since student response data is POSTed here in full.';
$string['apitimeout']      = 'Analytics service timeout (seconds)';
$string['apitimeout_desc'] = 'How long to wait for the analytics service before giving up.';

$string['selectquestion'] = 'Question';
$string['selectpart']     = 'Part';
$string['selectstudent']  = 'Student drill-down';
$string['selectstudentnone'] = 'None';
$string['gobutton']       = 'Go';

$string['colorblindmode'] = 'Colorblind mode';
