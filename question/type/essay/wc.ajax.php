<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

require_sesskey();

$res = array();
foreach ($_POST as $key => $value) {
    if (!empty(preg_grep('/q.*_answer/', array($key)))) {
        $res[$key] = array("words" => count_words($value), "characters" => count_letters($value));
    }
}
echo json_encode($res);
