<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace local_quizanalytics\analytics;

defined('MOODLE_INTERNAL') || die();

/** Builds the course-wide Moodle Facility Index heatmap payload. */
class question_difficulty {
    /**
     * Wrap long quiz names for the heatmap axis without changing the actual
     * quiz values used by Plotly for hover text and cell lookup.
     *
     * @param string $label
     * @return string
     */
    private static function wrap_quiz_label(string $label): string {
        $label = trim((string) preg_replace('/\s+/', ' ', $label));
        if ($label === '') {
            return $label;
        }

        $words = preg_split('/\s+/', $label);
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            if ($line !== '' && strlen($line . ' ' . $word) > 26) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $line === '' ? $word : $line . ' ' . $word;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return implode('<br>', $lines);
    }

    /**
     * @param array[] $rows Moodle-sourced facility records.
     * @return array|null
     */
    public static function build_section(array $rows): ?array {
        if (empty($rows)) {
            return null;
        }

        $quiznames = [];
        $questionnumbers = [];
        $bycell = [];
        foreach ($rows as $row) {
            $quiz = (string) $row['quiz_name'];
            $question = (string) $row['question_number'];
            $quiznames[$quiz] = true;
            $questionnumbers[$question] = (int) $row['question_slot'];
            $bycell[$quiz . '|' . $question] = $row;
        }
        $quiznames = array_keys($quiznames);
        $quizticktext = array_map([self::class, 'wrap_quiz_label'], $quiznames);
        uksort($questionnumbers, static fn($a, $b) => $questionnumbers[$a] <=> $questionnumbers[$b]);
        $questionnumbers = array_keys($questionnumbers);

        $z = [];
        $text = [];
        $customdata = [];
        $lowestquestion = null;
        $quizvalues = [];
        foreach ($quiznames as $quiz) {
            $zrow = [];
            $textrow = [];
            $customrow = [];
            $quizfacilities = [];
            foreach ($questionnumbers as $question) {
                $cell = $bycell[$quiz . '|' . $question] ?? null;
                $facility = $cell['facility_index'] ?? null;
                $zrow[] = $facility;
                $textrow[] = $facility === null ? 'N/A' : sprintf('%.1f%%', $facility);
                $customrow[] = [
                    $question,
                    s((string) ($cell['question_name'] ?? '')),
                    $facility === null ? 'N/A' : sprintf('%.1f%%', $facility),
                ];
                if ($facility !== null) {
                    $quizfacilities[] = $facility;
                    if ($lowestquestion === null || $facility < $lowestquestion['facility_index']) {
                        $lowestquestion = [
                            'quiz_name' => $quiz,
                            'question_number' => $question,
                            'question_name' => (string) $cell['question_name'],
                            'facility_index' => $facility,
                        ];
                    }
                }
            }
            $z[] = $zrow;
            $text[] = $textrow;
            $customdata[] = $customrow;
            if (!empty($quizfacilities)) {
                $quizvalues[$quiz] = array_sum($quizfacilities) / count($quizfacilities);
            }
        }

        $lowestquiz = null;
        foreach ($quizvalues as $quiz => $average) {
            if ($lowestquiz === null || $average < $lowestquiz['average']) {
                $lowestquiz = ['quiz_name' => $quiz, 'average' => $average];
            }
        }

        $notes = [];
        if ($lowestquestion !== null) {
            $notes[] = 'Most challenging question: ' . s($lowestquestion['quiz_name']) . ' — ' .
                s($lowestquestion['question_number']) . ' ' . s($lowestquestion['question_name']) .
                ' (' . sprintf('%.1f%%', $lowestquestion['facility_index']) . ').';
        }
        if ($lowestquiz !== null) {
            $notes[] = 'Most challenging quiz: ' . s($lowestquiz['quiz_name']) .
                ' (average Facility Index ' . sprintf('%.1f%%', $lowestquiz['average']) . ').';
        }

        return [
            'id' => 'question-difficulty',
            'title' => 'Moodle Facility Index',
            'caption' => 'Shows Moodle\'s Facility Index for each question across the STACK quizzes. Lower values indicate more challenging questions.',
            'caption_link' => [
                'url' => 'https://docs.moodle.org/en/Quiz_statistics',
                'label' => 'Moodle Quiz Statistics documentation',
            ],
            'notes' => $notes,
            'charts' => [[
                'id' => 'question-difficulty-fig',
                'title' => null,
                'plotly_json' => [
                    'data' => [[
                        'type' => 'heatmap',
                        'x' => $questionnumbers,
                        'y' => $quiznames,
                        'z' => $z,
                        'text' => $text,
                        'customdata' => $customdata,
                        'zmin' => 0,
                        'zmax' => 100,
                        'colorscale' => [
                            [0, '#b2182b'],
                            [0.25, '#ef8a62'],
                            [0.5, '#fddbc7'],
                            [0.75, '#67a9cf'],
                            [1, '#2166ac'],
                        ],
                        'hoverongaps' => true,
                        'hovertemplate' => 'Quiz: %{y}<br>Question: %{customdata[0]}<br>Question name: %{customdata[1]}<br>Facility Index: %{customdata[2]}<extra></extra>',
                        'texttemplate' => '%{text}',
                        'colorbar' => ['title' => ['text' => 'Facility Index (%)']],
                    ]],
                    'layout' => [
                        'title' => ['text' => 'Moodle Facility Index by Question'],
                        'xaxis' => ['title' => ['text' => 'Question']],
                        'yaxis' => [
                            'title' => ['text' => ''],
                            'tickmode' => 'array',
                            'tickvals' => $quiznames,
                            'ticktext' => $quizticktext,
                        ],
                        'margin' => ['l' => 220, 'r' => 100, 't' => 80, 'b' => 70],
                        'height' => max(420, 90 + count($quiznames) * 58),
                    ],
                ],
            ]],
        ];
    }
}
