<?php 
header('Content-Type: text/plain');

require_once "tools.php";

if (!$isstaff) {
    http_response_code(403);
    die('staff use only');
}

function showOne($qid) {
    $qobj = qparse($qid);
    if ($qobj['due'] > time() || $qobj['keyless']) return;
    
    $outof = 0; foreach($qobj['q'] as $mq) foreach($mq['q'] as $q) $outof += $q['points'];

    echo "compid,Quiz $qid [$outof],comments\n";
    
    foreach(glob("log/$qid/*.log") as $j=>$logname) {
        $student = pathinfo($logname, PATHINFO_FILENAME);
        if (!preg_match('/^[a-z]*[0-9][a-z]*$/', $student)) continue; // needed?
        $score = grade($qobj, $student)*$outof;
        echo "$student,$score,\n";
    }
}

function showAll() {
    $took = array();
    $qids = array();

    echo "compid";
    foreach(glob("questions/*.md") as $i=>$fname) {
        $qid = pathinfo($fname, PATHINFO_FILENAME);
        $qobj = qparse($qid);
        
        if ($qobj['due'] > time() || $qobj['keyless']) continue;
        $qids[] = $qid;
        
        $outof = 0; foreach($qobj['q'] as $mq) foreach($mq['q'] as $q) $outof += $q['points'];
        $worth[$qid] = $outof;

        echo ",Quiz $qid [$outof]";
        
        foreach(glob("log/$qid/*.log") as $j=>$logname) {
            $student = pathinfo($logname, PATHINFO_FILENAME);
            if (!preg_match('/^[a-z]*[0-9][a-z]*$/', $student)) continue; // needed?
            if (!array_key_exists($student, $took)) $took[$student] = array();

            $sid = $student;
            $took[$sid][$qid] = grade($qobj, $student)*$outof;
        }
    }
    
    foreach($took as $compid=>$scores) {
        echo "\n$compid";
        foreach($qids as $qid) {
            echo ",".(isset($scores[$qid])? $scores[$qid] : 0);
        }
    }
    echo "\n";
}

if (array_key_exists('quiz', $_GET)) {
    showOne($_GET['quiz']);
} else {
    showAll();
}
?>
