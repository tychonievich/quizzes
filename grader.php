<?php
/**
 * Have a function that finds (student, question wrong with comments, answer, comments) and logs it
 * Have a function that reads the logs and shows to graders
 *  - can add more comments 
 *  - and/or set new grade
 *  - and/or mark for additional review
 * Add grade-adjustment parsing to histogram.grade()
 */

if (php_sapi_name() == "cli") {
    parse_str(implode('&', array_slice($argv, 1)), $_GET);
    $_SERVER['REQUEST_URI'] = '.';
    $user = 'lat7h';
    $isstaff = true;
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
require_once "Markdown.php";
JSON::$use = SERVICES_JSON_LOOSE_TYPE;
require_once "histogram.php";


?><html>
    <head>
    <title>Quiz Grader</title>
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
        #clock { position:fixed; right:0px; top:0px; background:#ff7; padding-left:0.5ex; padding-bottom:0.5ex; border-bottom-left-radius:1ex; border-left: 1px solid black; border-bottom: 1px solid black;}
        .correct { background-color: #bfb; padding: 0ex 1ex; }
        .incorrect { background-color: #fbb; padding: 0ex 1ex; }
        .hist { color:#777; min-width: 2em; text-align:right; display:inline-block; }
    </style>
<script type="text/javascript">//<!--
function grade(user) {
    var val = document.querySelector('input[name="grade-'+user+'"]:checked');
    if (!val) return false;
    val = val.value;
    if (val == '(other)') {
        var txt = document.querySelector('input[name="other-'+user+'"]').value;
        var score = document.querySelector('input[name="score-'+user+'"]').value;
        if (score && !(score >= 0 && score <= 1)) return false;
        if (!txt.trim()) return false;
        
        var forms = document.getElementsByTagName('form');
        for(var i = 0; i < forms.length; i+=1) {
            var uid = forms[i].getAttribute('id').split('-')[0];
            var newopt = document.createElement('input');
            newopt.setAttribute('type','radio');
            newopt.setAttribute('name','grade-'+uid);
            newopt.setAttribute('value',score+';'+txt);
            var other = document.getElementById("other-for-"+uid);
            forms[i].insertBefore(newopt, other);
            forms[i].insertBefore(document.createTextNode(txt+' ('+score+')'), other);
            forms[i].insertBefore(document.createElement('br'), other);
        }
    } else {
        var idx = val.indexOf(';');
        var score = val.substr(0, idx);
        var txt = val.substr(idx+1);
    }
    var ans = [
        <?php echo "'$_GET[qid]', '$_GET[slug]',"; ?>
        user, score, txt,
    ];
    console.log("sending: ", JSON.stringify(ans));
    ajaxSend(JSON.stringify(ans), user);
    document.getElementById('q-'+user).className = "question submitting";
}

function ajaxSend(data, user) {
	var xhr = new XMLHttpRequest();
	if (!("withCredentials" in xhr)) {
		return null;
	}
	xhr.open("POST", "grader_report.php", true);
	xhr.withCredentials = true;
    xhr.setRequestHeader("Content-type", 'application/json');
    xhr.setRequestHeader("Content-length", data.length);
	xhr.onerror = function() {
		console.log("auto-check for new data broken");
	}
    xhr.onreadystatechange = function() { 
        if(xhr.readyState == 4 && xhr.status == 200) {
            document.getElementById('q-'+user).className = "question submitted";
            console.log("response: " + xhr.responseText);
        }
    }
	xhr.send(data);
}
//--></script>
    </head>
<body><?php 


/** returns
 * {'slug1':{'mst3k':{'comment':'bad question','score':0.0}, }
 * ,'slug2':{'mst3k':{'comment':'bad question','score':0.0}, }
 * }
 */
function getReviewSheet($num) {
    if (basename($num) != $num || !file_exists("log/$num")) return FALSE;
    if (file_exists("log/$num/review.json")) return JSON::parse(file_get_contents("log/$num/review.json"));
    
    $qs = array();
    $h = qparse("questions/$num.md", $qs);
    $due = new DateTime($h['due']);
    $now = new DateTime();
    if ($now < $due) return FALSE;
    
    
    $review = array();

    foreach($qs as $idx=>$mq) {
        foreach($mq["parts"] as $idx2=>$q) {
            $parts[$q['slug']] = $q;
            $review[$q['slug']] = array('-'=>$q);
        }
    }

    foreach(glob("log/$num/*.log") as $j=>$logname) { 
        $ans = getAnswers($logname);
        $user = basename($logname,".log");
        foreach($ans as $q => $a) {
            $score = grade($review[$q]['-'], $a);
            if ($score < 1 && array_key_exists('comments', $a) && trim($a['comments'])) {
                $review[$q][$user] = array('comment'=>$a['comments'], 'score'=>$score, 'answer'=>$a['answer']);
            }
        }
    }
    
    file_put_contents("log/$num/review.json", json_encode($review));
    
    return $review;    
}

/** Returns the number resolved and the number requesting resolution */
function ratioGraded_Question($commenters, $comments) {
    $count = 0.0;
    $responded_to = 0.0;
    foreach($commenters as $user => $com_score) {
        if ($user == '-') continue;
        $count += 1;
        $responded_to += array_key_exists($user, $comments['ans']);
    }
    return array($responded_to, $count);
}

/** returns
 * {'mst3k':{'score':'1.0', 'comment':'you got it', grader:'lat7h', date:'2016-08-31 14:08'}
 * ,'lat7h':{'score':'', 'comment':'off-topic comment', grader:'lat7h', date:'2016-08-31 14:08'}
 * }
 */
function getGraderComments($num, $q) {
    $answer = array();
    $key = array();
    $key['your comment does not change which answer is correct'] = "";
    $key['your comment is not actually true'] = "0.0";
    $key['your comment suggests partial understanding of the topic'] = "0.5";
    $key['your comment suggests you understood the topic'] = "1.0";

    if (basename($num) != $num || !file_exists("log/$num")) return array('ans'=>$answer, 'key'=>$key);
    if (basename($q) != $q || !file_exists("log/$num/adjustments_$q.log")) return array('ans'=>$answer, 'key'=>$key);
    
    
    // user,score,comments,grader,date -- score may be "" or a number
    $fh = fopen("log/$num/adjustments_$q.log", "rb");
    while(($data = fgetcsv($fh)) !== FALSE) {
        if (count($data) != 5) continue;
        $answer[$data[0]] = array('score'=>$data[1], 'comment'=>$data[2], 'grader'=>$data[3], 'date'=>$data[4]);
        $key[$data[2]] = $data[1];
    }
    $key['your comment does not change which answer is correct'] = "";
    $key['your comment used option letters, which were randomly assigned and are not accessible during grading'] = "";
    $key['your comment is not actually true'] = "0.0";
    $key['your comment suggests partial understanding of the topic'] = "0.5";
    $key['your comment suggests you understood the topic'] = "1.0";
    
    return array('ans'=>$answer, 'key'=>$key);
}

/** Renders one student answer item */
function showStudentAnswer($u, $q, $a) {
    
    echo '<div class="description">';
    $md = trim($q['text']);
    $md =  "<span class=\"hist\">(score: ".round($a['score'],2).")</span> " . $md;
    if ($q['checkbox']) $md .= "    \n**Select all that apply**";
    echo MarkdownExtra::defaultTransform("**Question**: " . $md);
    echo '</div><div class="options" id="o-'.$u.'">';
    if (array_key_exists('choices', $q)) {
        echo '<ol class="options">';
        foreach($q['choices'] as $key=>$val) {
            if ($val['remove'] || $val['chosen']) continue;
            echo "\n<li>";
            $chosen = array_key_exists('answer', $a) && in_array($val['slug'], $a['answer']);
            if ($val['correct']) 
                if ($chosen) echo '<span class="correct">☑</span>';
                else echo '<span class="incorrect">☑</span>';
            else 
                if ($chosen) echo '<span class="incorrect">☐</span>';
                else echo '<span class="correct">☐</span>';
            echo '<input disabled="disabled" type="';
            echo $q['checkbox'] ? 'checkbox' : 'radio';
            echo '" name="ans-'.$u.'" value="'.$val['slug'].'"';
            if ($chosen) echo ' checked="checked"';
            echo '></input> ';
            echo MarkdownExtra::defaultTransform($val['text']);
            echo '</li>';
        }
        echo '</ol>';
    } else {
        $correctAns = false;
        
        echo 'Answer: <input disabled="disabled" type="text" name="ans-'.$u.'"';
        if (array_key_exists('answer', $a)) {
            echo ' value="'.htmlentities($a['answer'][0]).'"';
            if ($q['solution'][0] == '/') {
                $correctAns = preg_match($q['solution'], $a['answer'][0]);
            } else {
                $correctAns = trim($q['answer'][0]) == $a['solution'];
            }
        }
        echo '/>';
        echo '(accepted answers: <code class="';
        if (!$correctAns) echo 'in';
        echo 'correct">'.htmlentities($q['solution']).'</code>)';
    }
    
    echo '<table><tbody><tr><td>Comments:</td><td width="100%"><textarea style="width:100%" disabled="disabled">';
    echo htmlentities($a['comment']);
    echo '</textarea></td></tr></tbody></table>';

    echo '</div>';
}

/** renders everything */
function showUI() {
    if (array_key_exists("qid", $_GET)) {
        $sheet = getReviewSheet($_GET['qid']);

        if (array_key_exists("slug", $_GET)) { // show questions to grade
            $toreview = $sheet[$_GET['slug']];
            $comments = getGraderComments($_GET['qid'], $_GET['slug']);
            
    //        echo '<pre>';
    //        echo json_encode($toreview, JSON_PRETTY_PRINT);
    //        echo '</pre>';
            
            for($pass=1; $pass<=2; $pass+=1) {
                foreach($toreview as $student => $answer) {
                    if ($student == '-') continue;
                    if (($pass == 2) != array_key_exists($student, $comments['ans'])) continue;
                    $class = ($pass == 2 ? "question submitted" : "question");
                    
                    echo '<div class="'.$class.'" id="q-'.$student.'">';
                    echo "<a href=\"quiz.php?qid=$_GET[qid]&asuser=$student\" target=\"_blank\">See this student's entire quiz</a><br/>";
                    showStudentAnswer($student, $toreview['-'], $answer);
                    
                    /*
                    echo '<pre>';
                    var_dump($student);
                    var_dump($comments['ans']);
                    echo '</pre>';
                    */
                    
                    echo "\n<form id='$student-regrade'>\n";
                    foreach($comments['key'] as $text => $score) {
                        echo "<input type='radio' name='grade-$student' value='$score;".htmlspecialchars($text)."'";
                        if ($pass == 2 && $comments['ans'][$student]['comment'] == $text) echo ' checked="checked"';
                        echo "/>". htmlspecialchars($text)." ($score)<br/>\n";
                    }
                    echo "<input type='radio' name='grade-$student' id='other-for-$student' value='(other)'/> Other: <input type='text' name='other-$student' size='40'/> (<input type='text' size='4' name='score-$student'/>)<br/>\n";
                    echo '<input type="button" value="submit grade" onclick="grade(\''.$student.'\')"/>';
                    echo "</form>\n";
                
                    echo '</div>';
                    flush();
                }
            }
            
            
        } else { // show menu of questions to grade
            ?><table><tbody><?php
            foreach($sheet as $slug=>$items) {
                $comments = getGraderComments($_GET['qid'], $slug);
                $ratio = ratioGraded_Question($items, $comments);
                $g = $ratio[0];
                $t = $ratio[1];
                $here = htmlspecialchars($_SERVER['REQUEST_URI'].'&slug='.$slug, ENT_QUOTES, 'UTF-8');
                echo "<tr><td><a href='$here'>$slug</a></td><td>graded</td><td style='text-align:right'>$g</td><td>of</td><td style='text-align:right'>$t</td><td>commented wrong answers</td></tr>";
                flush();
            }
            ?></tbody></table><?php
        }

    } else { // show menu of quizzes to grade
        // TODO: fix this
    }

}

showUI();

?></body></html>
