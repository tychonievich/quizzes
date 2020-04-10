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
function all_grades($pad = FALSE, &$rubric=FALSE) {
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
            if ($rubric !== FALSE && !isset($rubric[$qid])) $hr = array();
            else $hr = False;
            foreach($qobj['q'] as $mq) foreach($mq['q'] as $q) {
                $qnum += 1;
                
                // grade always sets ['score'], even if student skipped the question, unless the question is ungradeable (dropped, image, text with no key, etc)
                if (!isset($sobj[$q['slug']]['score'])) $weight = 0;
                else $weight = $q['points'];
                $ratio = $weight ? $sobj[$q['slug']]['score'] / $weight : 1;
                
                $name = "Question $qnum (worth $weight pt)";
                
                if ($hr !== FALSE) $hr[] = array(
                    'name'=>$name,
                    'weight'=>$weight,
                );

                $quizzes[$qid][$sid][] = 
                    array(
                        'ratio'=> $ratio,
                        'weight'=>$weight,
                        'name'=>$name,
                    );
            }
            if ($hr !== FALSE) $rubric[$qid] = $hr;
        
        }
    }
    if ($pad) {
        foreach($quizzes as $k=>&$val)
            foreach($everyone as $uid=>$true)
                if (!isset($val[$uid])) $val[$uid] = false;
    }
    return $quizzes;
}

/** Experimental and dangerous: creates, and overrides if present, "$prefix$slug/$user/.grade" */
function post_grades($prefix, $special=array()) {
    global $metadata;
    $rubrics = array();
    foreach(all_grades(true, $rubrics) as $qid => $users) {
        if (qparse($qid)['keyless']) continue;
        if (isset($special[$qid]) && !$special[$qid]) continue;
        
        $dir = "$prefix$qid/";
        $qname = "$metadata[quizname] $qid";
        
        if (isset($special[$qid])) {
            $dir = dirname($prefix)."/$special[$qid]/";
            $qname = "$special[$qid]";
        }
        
        file_put_contents_recursive("$dir.rubric",  json_encode(array(
            "kind"=>"hybrid",
            "late-penalty"=>1,
            "auto-weight"=>0,
            "human"=>$rubrics[$qid],
        )));
        
        foreach($users as $user=>$human) {
            //if ($user != 'lat7h') continue;
            if ($human === false ) {
                file_put_contents_recursive("$dir$user/.grade", '{"kind":"percentage","ratio":0,"comments":"did not take '.$qname.'"}');
            } else {
                file_put_contents_recursive("$dir$user/.grade",
                json_encode(array(
                    "kind"=>"hybrid",
                    "auto"=>1,
                    "auto-late"=>1,
                    "late-penalty"=>1,
                    "auto-weight"=>0,
                    "human"=>$human,
                    "comments"=>"see the quizzing site for details",
                )));
            }
        }
    }
}

if (php_sapi_name() == "cli") { // command line
    // record as grades
    post_grades("../uploads/Quiz", array('exam2'=>'Exam2', 'labs'=>false));
} else if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    // display
    header('Content-Type: text/plain; charset=utf-8'); 
    echo json_encode(all_grades(), JSON_PRETTY);
} else {
    // show nothing, just define the functions
}


?>
