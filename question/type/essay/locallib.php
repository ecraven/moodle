<?php

defined('MOODLE_INTERNAL') || die();

function qtype_essay_format_wordcount($id, $charlimit, $wordlimit, $text) {
    $result = html_writer::start_tag('div', array('class' => 'wordcount',
                                                  'name' => 'wordcount',
                                                  'id' => $id . "_wordcount"));
    $chars = count_letters($text);
    $result .= html_writer::start_tag('span', array('class' => "wordcount " . ($chars <= $charlimit ? "underlimit" : "overlimit")));
    $result .= ($chars <= $charlimit ? "✓" : "×");
    $result .= get_string('characters', 'qtype_essay') . ': ' . $chars
            . ' / ' . $charlimit;
    $result .= html_writer::end_tag('span');
    $result .= ', ';
    $words = count_words($text);
    $result .= html_writer::start_tag('span', array('class' => "wordcount " . ($words <= $wordlimit ? "underlimit" : "overlimit")));
    $result .= ($words <= $wordlimit ? "✓" : "×");
    $result .= get_string('words', 'qtype_essay') . ': ' . $words
            . ' / ' . $wordlimit;
    $result .= html_writer::end_tag('span');
    $result .= html_writer::end_tag('div');
    return $result;
}
