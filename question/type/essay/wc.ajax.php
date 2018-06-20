<?php
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/locallib.php');

require_sesskey();

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}

function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== false);
}
$res = array();
foreach ($_POST as $key => $value) {
    if(startsWith($key, "q")
       && endsWith($key, "_answer")) {
        $res[$key] = qtype_essay_format_wordcount($key, 5, 10, $value);
    }
}
echo json_encode($res);
