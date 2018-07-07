<?php 
header('Content-Type: text/plain');

if (php_sapi_name() == "cli") {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
} else {
    require_once "staff.php";
    if (!$isstaff) {
        http_response_code(403);
        echo 'staff use only';
        exit;
    }
}

require_once "qparse.php";
require_once "JSON.php";
require_once "histogram.php";
JSON::$use = SERVICES_JSON_LOOSE_TYPE;


function gradeQuiz($whom, $which, $qs) {
    $logname = "log/$which/$whom.log";
    if (!file_exists($logname)) return 0;
    $answers = getAnswers($logname);
    if (empty($answers)) return 0;
    $score = 0;
    foreach($qs as $key=>$val) {
        foreach($val['parts'] as $key2=>$part) {
            if (array_key_exists($part['slug'], $answers))
                $score += grade($part, $answers[$part['slug']]);
            else
                $score += grade($part, NULL);
        }
    }
    return $score;
}

function showOne($number) {
    $number = pathinfo($number, PATHINFO_FILENAME);
    $fname="questions/$number.md";
    if (!file_exists($fname)) return;
    $qs = array();
    $h = qparse($fname, $qs);
    
    if (strtotime($h['due']) > time()) return;

    $outof = 0;
    foreach($qs as $key=>$val) {
        foreach($val['parts'] as $key2=>$part) {
            $outof += $part['points'];
        }
    }

    echo "compid,Quiz $number [$outof],comments\n";
    
    foreach(glob("log/$number/*.log") as $j=>$logname) {
        $student = pathinfo($logname, PATHINFO_FILENAME);
        if (!preg_match('/^[a-z]*[0-9][a-z]*$/', $student)) continue;
        $score = gradeQuiz($student, $number, $qs);
        echo "$student,$score,\n";
    }
}

if (array_key_exists('quiz', $_GET)) {
    showOne($_GET['quiz']);
} else {
    $took = array();
    echo "compid";
    foreach(glob("questions/*.md") as $i=>$fname) {
        $qs = array();
        $h = qparse($fname, $qs);
        if (strtotime($h['due']) > time()) continue;

        $outof = 0;
        foreach($qs as $key=>$val) {
            foreach($val['parts'] as $key2=>$part) {
                $outof += $part['points'];
            }
        }

        $number = pathinfo($fname, PATHINFO_FILENAME);
        echo ",Q$number [$outof]";

        foreach(glob("log/$number/*.log") as $j=>$logname) {
            $student = pathinfo($logname, PATHINFO_FILENAME);
            if (!preg_match('/^[a-z]*[0-9][a-z]*$/', $student)) continue;
            if (!array_key_exists($student, $took)) {
                $took[$student] = array();
            }
            $took[$student][$number] = gradeQuiz($student, $number, $qs);
        }
    }
    foreach($took as $compid=>$scores) {
        echo "\n$compid";
        foreach(glob("questions/*.md") as $i=>$fname) {
            $number = pathinfo($fname, PATHINFO_FILENAME);
            if (array_key_exists($number, $scores)) {
                echo ",".$scores[$number];
            } else {
                echo ",";
            }
        }
    }
    
}
?>
