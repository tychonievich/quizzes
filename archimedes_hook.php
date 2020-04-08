<?php 
header('Content-Type: text/plain');

/**
 * This file is specifically to help convert from this tool
 * to the hybrid rubrics of https://github.com/tychonievich/archimedes
 * It does not do the full job, just enough to make my draft hooks work.
 * Full integration may be added at a future time.
 */

require_once "tools.php";

if (!$isstaff) {
    http_response_code(403);
    die('staff use only');
}

/** Return an array of the form
 * 
 * {slug:{user:[{"ratio":1,"weight":2,"name":"Question 8"},...],...},...}
 * 
 * if given a first argument that is true, adds user:false for each user who ever viewed any quiz
 */
function all_grades($pad = FALSE) {
    $quizzes = array();
    if ($pad) $everyone = array();
    foreach(glob("questions/*.md") as $i=>$fname) {
        $qid = pathinfo($fname, PATHINFO_FILENAME);
        $qobj = qparse($qid);
        
        if ($qobj['due'] > time() || $qobj['keyless']) continue;
        
        $quizzes[$qid] = array();
        
        foreach(glob("log/$qid/*.log") as $j=>$logname) {
            $sid = pathinfo($logname, PATHINFO_FILENAME);
            if ($pad) $everyone[$sid] = true;

            $quizzes[$qid][$sid] = array();
            $sobj = aparse($qobj, $sid);
            grade($qobj, $sobj);
            
            $qnum = 0;
            foreach($qobj['q'] as $mq) foreach($mq['q'] as $q) {
                $qnum += 1;
                
                // grade always sets ['score'], even if student skipped the question, unless the question is ungradeable (dropped, image, text with no key, etc)
                if (!isset($sobj[$q['slug']]['score'])) $weight = 0;
                else $weight = $q['points'];
                $ratio = $weight ? $sobj[$q['slug']]['score'] / $weight : 1;

                $quizzes[$qid][$sid][] = 
                    array(
                        'ratio'=> $ratio,
                        'weight'=>$weight,
                        'name'=>"Question $qnum",
                    );
            }
        }
    }
    if ($pad) {
        foreach($quizzes as $k=>&$val)
            foreach($everyone as $uid=>$true)
                if (!isset($val[$uid])) $val[$uid] = false;
    }
    return $quizzes;
}

function show_grades() {
    echo json_encode(all_grades(), JSON_PRETTY);
}

show_grades()

?>
