<?php
/**
 * English language strings for quiz_quizanalytics.
 *
 * @package quiz_quizanalytics
 */

defined('MOODLE_INTERNAL') || die();

// pluginname is what shows up as the tab label on the quiz results page —
// "Grades | Responses | Statistics | Interactive Analytics" — and in the
// Site Administration > Plugins > Quiz reports list.
$string['pluginname'] = 'Interactive Analytics';

// Required by mod/quiz/lib.php's report tab/dropdown builder, which calls
// get_string($report, 'quiz_' . $report) — i.e. a BARE key matching the
// report's folder name exactly, no suffix. This is a different convention
// from quizanalyticsreport below; both are needed.
$string['quizanalytics'] = 'Interactive Analytics';

// Required by mod/quiz/settings.php, which builds the "Quiz reports" admin
// list via get_string($report . 'report', 'quiz_' . $report) — every core
// quiz report defines this pattern too (quiz_overview -> 'overviewreport',
// quiz_statistics -> 'statisticsreport'). Without it, that get_string() call
// fails while Moodle builds the Activity modules admin tree.
$string['quizanalyticsreport'] = 'Interactive Analytics report';

$string['quizanalytics:view'] = 'View interactive quiz analytics';

$string['noattempts']    = 'No finished attempts yet. Analytics will appear once at least one student has completed the quiz.';
$string['servererror']   = 'The analytics service could not be reached. Contact your Moodle administrator.';
$string['loaderror']     = 'The analytics service returned an unexpected response.';

$string['apiurl']        = 'Analytics service URL';
$string['apiurl_desc']   = 'Internal URL of the analytics microservice\'s /analyze endpoint. This should always point at localhost or a private/internal network address — never a public URL — since student response data is POSTed here in full.';
$string['apitimeout']    = 'Analytics service timeout (seconds)';
$string['apitimeout_desc'] = 'How long to wait for the analytics service before giving up.';
$string['apipdftimeout'] = 'PDF export timeout (seconds)';
$string['apipdftimeout_desc'] = 'How long to wait for a PDF report to be generated before giving up. PDF generation rasterizes every chart, so it takes noticeably longer than a normal page load.';

$string['colorblindmode'] = 'Colorblind mode';

$string['generatepdfheading'] = 'Generate PDF Report';
$string['downloadpdfbutton']  = 'Download PDF';
$string['pdferror']           = 'The PDF report could not be generated. Contact your Moodle administrator.';
