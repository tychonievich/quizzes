<?php 
/** Functions for showing quiz questions */

require_once "qparse.php";
require_once "Markdown.php";
require_once "JSON.php";
JSON::$use = SERVICES_JSON_LOOSE_TYPE;
require_once "histogram.php";

function showPart($name, $num, $part, $comments, $stat) {
    global $isstaff;
    if (!$stat) $stat = array();
    
    echo '<div class="question" id="q'.$num.'" slug="'.$part['slug'].'">';
    echo '<div class="description" id="d'.$num.'">';
    $md = trim($part['text']);
    if ($part['subpart']) $md = "(see above) ".$md;
    if ($part['checkbox']) $md .= "    \n**Select all that apply**";
    echo MarkdownExtra::defaultTransform("**Question $num**: " . $md);
    echo '</div><div class="options" id="o'.$num.'">';
    $postcall = "postAns(".htmlspecialchars(json_encode($name)).", $num)";
    if (array_key_exists('choices', $part)) {
        echo '<ol class="options">';
        if (!$isstaff) shuffleAnswers($part['choices']);
        foreach($part['choices'] as $key=>$val) {
            if ($val['remove']) continue;
            echo "\n<li>";
            if ($part['checkbox']) {
                echo '<input type="checkbox" name="ans'.$num.'" value="'.$val['slug'].'" onchange="'.$postcall.'"';
                if (array_key_exists('answer', $stat) && in_array($val['slug'], $stat['answer']))
                    echo ' checked="checked"';
                echo '></input> ';
            } else {
                echo '<input type="radio" name="ans'.$num.'" value="'.$val['slug'].'" onchange="'.$postcall.'"';
                if (array_key_exists('answer', $stat) && in_array($val['slug'], $stat['answer']))
                    echo ' checked="checked"';
                echo '></input> ';
            }
            echo MarkdownExtra::defaultTransform($val['text']);
            echo '</li>';
        }
        echo '</ol>';
    } else {
        echo 'Answer: <input type="text" name="ans'.$num.'" onchange="'.$postcall.'" onkeydown="pending('.$num.')"';
        if (array_key_exists('answer', $stat))
            echo ' value="'.htmlentities($stat['answer'][0]).'"';
        echo '/>';
    }
    if($comments) {
        echo '<table class="blank"><tbody><tr><td>Comments:</td><td width="100%"><textarea id="comments'.$num.'" style="width:100%" onchange="'.$postcall.'" onkeydown="pending('.$num.')">';
        if (array_key_exists('comments', $stat))
            echo htmlentities($stat['comments']);

        echo '</textarea></td></tr></tbody></table>';
    }
    echo '</div></div>';
}

function showAnswers($name, $num, $part, $comments, $stat, $withKey, $hideChosen, $notTaken) {
    if (!$stat) $stat = array();
    
    $hist = histOf($name)[$part['slug']];
    
    echo '<div class="question" id="q'.$num.'" slug="'.$part['slug'].'">';
    echo '<div class="description" id="d'.$num.'">';
    $md = trim($part['text']);
    if ($part['subpart']) $md = "(see above) ".$md;
    if ($notTaken) {
        $rawscore = 0;
        $score = 0;
    } else {
        $rawscore = round(grade($part, $stat, FALSE),2);
        $score = round(grade($part, $stat, TRUE),2);
    }
    if (!$hideChosen) {
        $md =  "<span class=\"hist\">(score: $score; mean: ".round($hist["right"]/$hist["total"],2).")</span> " . $md;
    }
    if ($part['checkbox']) $md .= "    \n**Select all that apply**";
    if ($part['points'] != 1) {
        echo MarkdownExtra::defaultTransform("**Question $num** ($part[points] points): " . $md);
    } else {
        echo MarkdownExtra::defaultTransform("**Question $num**: " . $md);
    }
    echo '</div><div class="options" id="o'.$num.'">';
    if (array_key_exists('choices', $part)) {
        echo '<ol class="options">';
        foreach($part['choices'] as $key=>$val) {
            #if ($val['remove']) continue;
            echo "\n<li>";
            if (!$hideChosen) {
                echo '<span class="hist">'.round(100*$hist[$val['slug']]/$hist['total']).'%</span> ';
            }
            if ($hideChosen) {
                $chosen = 0;
            } else {
                $chosen = array_key_exists('answer', $stat) && in_array($val['slug'], $stat['answer']);
            }
            if ($part['checkbox']) {
                if ($withKey) {
                    if ($val['remove']) echo '(dropped from quiz)';
                    if ($val['free']) echo '(accepted any answer)';
                    if ($val['correct']) echo '(correct answer)'; 
                    /*
                    if ($val['correct']) 
                        if ($chosen) echo '<span class="correct">☑</span>';
                        else echo '<span class="incorrect">☑</span>';
                    else 
                        if ($chosen) echo '<span class="incorrect">☐</span>';
                        else echo '<span class="correct">☐</span>';
                    */
                }
                echo '<input disabled="disabled" type="checkbox" name="ans'.$num.'" value="'.$val['slug'].'"';
                if ($chosen) echo ' checked="checked"';
                echo '></input> ';
            } else {
                if ($withKey) {
                    if ($val['remove']) echo '(dropped from quiz)';
                    if ($val['correct']) echo '(correct answer)'; 
                    /*
                    if ($val['correct']) 
                        if ($chosen) echo '<span class="correct">☑</span>';
                        else echo '<span class="incorrect">☑</span>';
                    else 
                        if ($chosen) echo '<span class="incorrect">☐</span>';
                        else echo '<span class="correct">☐</span>';
                    */
                }
                echo '<input disabled="disabled" type="radio" name="ans'.$num.'" value="'.$val['slug'].'"';
                if ($chosen) echo ' checked="checked"';
                echo '></input> ';
            }
            echo MarkdownExtra::defaultTransform($val['text']);
            echo '</li>';
        }
        echo '</ol>';
        if ($withKey && array_key_exists('solution', $part)) {
            echo '<p>'.MarkdownExtra::defaultTransform($part['solution']).'</p>';
        }
    } else {
        $correctAns = false;
        
        echo 'Answer: <input disabled="disabled" type="text" name="ans'.$num.'"';
        if (array_key_exists('answer', $stat)) {
            echo ' value="'.htmlentities($stat['answer'][0]).'"';
            if ($part['solution'][0] == '/') {
                $correctAns = preg_match($part['solution'], $stat['answer'][0]);
            } else {
                $correctAns = trim($stat['answer'][0]) == $part['solution'];
            }
        }
        echo '/>';
        if ($withKey) {
            echo '(accepted answers: <code class="';
            if (!$correctAns) echo 'in';
            echo 'correct">'.htmlentities($part['solution']).'</code>)';
        }
    }
    if($comments && array_key_exists('comments', $stat) && strlen($stat['comments']) > 0) {
        echo '<table class="blank"><tbody><tr><td>Comments:</td><td width="100%"><textarea id="comments'.$num.'" style="width:100%" disabled="disabled">';
        echo htmlentities($stat['comments']);
        echo '</textarea></td></tr></tbody></table>';
    }
    if (array_key_exists('grade', $stat)) {
        echo '<p style="padding-left:1em; text-indent:-1em;"><strong>Grader reply:</strong> ' . htmlentities($stat['feedback']);
        if ($score == $rawscore) {
            echo " <em>(no change in grade)</em>";
        } else {
            echo " <em>(changed grade from $rawscore to $score)</em>";
        }
    }
    echo '</div></div>';
}


function shuffleAnswers(&$choices) {
    $i = count($choices) - 1;
    while ($i >= 0) {
        if (strpos($choices[$i]['text'], 'of the above') !== FALSE) {
            $i -= 1;
        } else {
            $j = rand(0,$i);
            $tmp = $choices[$i];
            $choices[$i] = $choices[$j];
            $choices[$j] = $tmp;
            $i -= 1;
        }
    }
}

function putlog($name, $line) {
    umask(0); // discouraged but important for mkdir
    mkdir(dirname("log/".$name), 0777, true);
    file_put_contents('log/'.$name, $line, FILE_APPEND);
    chmod('log/'.$name, 0666);
}

function lastStatus($name) {
    global $user;
    if (!file_exists("log/$name/$user.log")) return array('date'=>date('Y-m-d H:i:s'));
    $ans = array();
    $fh = fopen("log/$name/$user.log", "rb");
    while(($jsdata = fgets($fh)) !== FALSE) {
        $obj = JSON::parse($jsdata);
        if (!array_key_exists('date', $ans) && array_key_exists('date', $obj))
            $ans['date'] = $obj['date'];
        if (array_key_exists('grade', $obj)) {
            $slug = $obj['slug'];
            if (!array_key_exists($slug, $ans)) continue;
            $ans[$slug]['grade'] = $obj['grade'];
            $ans[$slug]['feedback'] = $obj['feedback'];
        } else if (array_key_exists('slug', $obj)) {
            $show = array('answer'=>$obj['answer']);
            if (array_key_exists('comments', $obj))
                $show['comments'] = $obj['comments'];
            else
                $show['comments'] = "";
            $ans[$obj['slug']] = $show;
        }
    }
    if (!array_key_exists('date', $ans)) $ans['date'] = date('Y-m-d H:i:s');
    return $ans;
}

function showQuiz($name, $hideChosen = 0, $hideKey = 0, $hideClock = 0) {
    global $user, $extra, $isstaff;
    if (strpos($name,'/') !== FALSE || strpos($name, "..") !== FALSE || !file_exists('questions/'.$name.'.md')) {
        ?><p style="text-align: center">
            Invalid quiz identifier
        </p><?php
        return;
    }
    
    $qs = array();
    $h = qparse('questions/'.$name.'.md', $qs);
    $comments = $h['comments'] == 'true';
    
    $stat = lastStatus($name);
    $started = strtotime($stat['date']);
    $hours = floatval($h['hours']);
    if (array_key_exists($user, $extra)) $hours *= $extra[$user];
    if ($hours == 0) $hours = 8765.8128; // 1 year
    $due1 = $started + ($hours*60*60);
    $due2 = strtotime($h['due']);
    $due = min($due1, $due2);
    $remaining = $due - time();


    if (strtotime($h['open']) > time() && !$isstaff) {
        ?><p style="text-align: center">
            This quiz has not yet opened.
        </p><?php
        return;
    } else if ($remaining <= 0 && $due2 >= time() && !$isstaff) {
        ?><p style="text-align: center">
            You have already taken this quiz;
            results will become available after it has closed.
        </p><?php
        return;
    } else if ($remaining > 0) {
        if ($user == $_SERVER['PHP_AUTH_USER'])
            putLog("$name/$user.log", '{"date":"'.date('Y-m-d H:i:s').'"}'."\n");
    }
   
    if (!$hideClock) { 
        echo '<div id="clock">'.$remaining.'</div>';
    }
    
    if ($h['directions']) {
        echo '<div class="directions">'.MarkdownExtra::defaultTransform($h['directions']).'</div>';
    }

    if ($remaining > 0 && !$isstaff) {
        srand(intval(substr(sha1($user),0,8), 16));
        shuffle($qs);
    }
    
    $num = 0;
    foreach($qs as $key=>$val) {
        if (count($val['parts']) == 0) continue;
        if (array_key_exists('text', $val)) {
            echo '<div class="multiquestion">';
            echo MarkdownExtra::defaultTransform($val['text']);
            if ($remaining > 0 && !$isstaff) { shuffle($val['parts']); }
            foreach($val['parts'] as $key2=>$part) {
                $num += 1;
                if ($due2 <= time()) {
                    showAnswers($name, $num, $part, $comments, $stat[$part['slug']], $due2 < time() && !$hideKey, $hideChosen, $started > $due);
                } else {
                    showPart($name, $num, $part, $comments, $stat[$part['slug']]);
                }
            }
            echo '</div>';
        } else {
            $num += 1;
            $part = $val['parts'][0];
            if ($due2 <= time()) {
                showAnswers($name, $num, $part, $comments, $stat[$part['slug']], $due2 < time() && !$hideKey, $hideChosen, $started > $due);
            } else {
                showPart($name, $num, $part, $comments, $stat[$part['slug']]);
            }
        }
    }
}
