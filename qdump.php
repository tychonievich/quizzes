<?php 
require_once "staff.php";
require_once "qshowlib.php";

if (php_sapi_name() == "cli") {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

preg_match('/\/([FS])(\d\d\d\d)\//', __FILE__, $sem_parts);

$hideKey = !$_GET['key'];

if (!$sem_parts) {
    $semester = "";
} else {
    $semester = " for ";
    if ($sem_parts[1] == 'F') {
        $semester .= "Fall ";
    } else {
        $semester .= "Spring ";
    }
    $semester .= $sem_parts[2];
}

?>

﻿<html>
<head>
    <title>All quizzes<?= $semester ?><?= $hideKey ? "" : " (key)" ?></title>
    <style>
        ol.options { margin-top:-1em; }
        ol.options li p { display: inline; }
        ol.options li { list-style: upper-alpha; }
        body { background:#ccc; padding-top:1em; }
        .question { background: white; border-radius:1ex; padding:0ex 1ex; margin:2ex;  }
        .directions { background: #eee; padding:1ex 1ex; margin:2ex;  }
        .multiquestion { background: #eee; border-radius:1ex; padding:0.7ex 0.7ex; }
        .multiquestion + .multiquestion { margin-top:1em; }
        .submitting { background: #fe7; }
        .submitted { background: #dfd; }
        textarea { background-color: inherit; }
        .correct { background-color: #bfb; padding: 0ex 1ex; }
        .incorrect { background-color: #fbb; padding: 0ex 1ex; }
        .hist { color:#777; min-width: 2em; text-align:right; display:inline-block; }
    </style>
</head>
    <h1>All quizzes<?= $semester ?><?= $hideKey ? "" : " (key)" ?></h1>

<?php
if ($isstaff) {
    foreach(glob('questions/*.md') as $i=>$name) {
        $name = basename($name,".md");
        echo '<h2>Quiz '.$name.'</h2><div class="quizFrame">';
        showQuiz($name, 1, $hideKey, 1);
        echo '</div>';
    }
} else {
    echo '<p>Sorry, this page is available only to staff.</p>';
}
?>
