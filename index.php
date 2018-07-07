<?php 
require_once "staff.php";
?>
﻿<html>
<head>
    <title>Quiz Index</title>
    <style>
        table { border-collapse: collapse; width:100%; }
        thead tr:last-child { border-bottom: thin black solid; }
        th { text-align:left;  padding:0ex 1ex; }
        tbody tr:nth-child(2n+1) { background-color:#ddd; }
        td { padding:1ex; }
        body { background:#ccc; padding-top:1em; }
    </style>
</head>
<body><?php

require_once "qparse.php";
require_once "JSON.php";
JSON::$use = SERVICES_JSON_LOOSE_TYPE;
require_once "histogram.php";


?>
<center><a href="<?=$homepage?>">Return to course page</a></center>
    <p>
        Welcome, <?=$user?>, to the quiz index page.
    </p>
    <p>
        This page is static, meaning its information is based on the time you loaded it.
        To update it you need to reload the page.
    </p>
<?php

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

function lastStatus($name) {
    global $user;
    if (!file_exists("log/$name/$user.log")) return array();
    $ans = array();
    $fh = fopen("log/$name/$user.log", "rb");
    if (!$fh) return $ans;
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

function durationString($time) {
    $base = new DateTime('1970-01-01');
    $base2= new DateTime('1970-01-01');
    $base2->add(new DateInterval('PT'.$time));
    if ($base == $base2) return 'unlimited';
    $dur = $base2->diff($base);
    $days = $dur->format('%a');
    if ($days == 1) return $dur->format("1 day");
    if ($days > 0) return $dur->format("$days days");
    return $dur->format('%h:%I');
}


function gradeQuiz($name) {
    global $user, $extra, $isstaff;
    $qs = array();
    $h = qparse('questions/'.$name.'.md', $qs);
    
    $due = new DateTime($h['due']);
    $open = new DateTime($h['open']);
    $now = new DateTime();
    $hours = floatval($h['hours']);
    if (array_key_exists($user, $extra)) $hours *= $extra[$user];
    $answer = array(
        $h['title'],
        $open->format('D j M g:ia'),
        $due->format('D j M g:ia'),
        durationString(intval($hours*60).'M')
    );

    if (!file_exists("log/$name/$user.log")) {
        if (array_key_exists('placeholder', $h)) {
            $answer[1] = '';
            $answer[2] = '';
            $answer[3] = '';
            $answer[] = 'no quiz';
        } else if ($now < $open) { $answer[] = 'not yet open'; }
        else if ($now < $due) { $answer[] = 'available'; }
        else { 
            $hist = histOf($name);
            if ($hist === FALSE) {
                $answer[] = "failed to take<br/>";
            } else {
                $answer[] = "failed to take<br/>(mean: ".round($hist["score"],2).")";
            }
        }
        return $answer;
    }
    
    if (array_key_exists('placeholder', $h)) {
        $answer[1] = '';
        $answer[2] = '';
        $answer[3] = '';
        $answer[] = 'no quiz';
        return $answer;
    }
    
    $stat = lastStatus($name);
    $started = strtotime($stat['date']);
    $hours = floatval($h['hours']);
    if ($hours == 0) $hours = 8765.8128; // 1 year
    if (array_key_exists($user, $extra)) $hours *= $extra[$user];
    $due1 = $started + ($hours*60*60);
    $due2 = strtotime($h['due']);
    $due = min($due1, $due2);
    $remaining = $due - time();
    
    if (strtotime($h['open']) > time()) {
        $answer[] = 'not yet open';
        return $answer;
    } else if ($remaining > 0) {
        $answer[] = 'in progress ('.durationString($remaining.'S').' remaining)';
        return $answer;
    } else if (!$isstaff && $due2 >= time()) {
        $answer[] = 'taken but not yet graded';
        return $answer;
    }
    
    srand(intval(substr(sha1($user),0,8), 16));
    shuffle($qs);
    
    $score = 0;
    $outof = 0;
    $num = 0;
    foreach($qs as $key=>$val) {
        if (array_key_exists('text', $val)) {
            shuffle($val['parts']);
            foreach($val['parts'] as $key2=>$part) {
                $outof += $part['points'];
                $answers = NULL;
                if (array_key_exists($part['slug'], $stat)) {
                    $answers = $stat[$part['slug']];
                }
                $score += grade($part, $answers);
            }
        } else {
            $part = $val['parts'][0];
            $outof += $part['points'];
            $answers = NULL;
            if (array_key_exists($part['slug'], $stat)) {
                $answers = $stat[$part['slug']];
            }
            $score += grade($part, $answers);
        }
    }
    $score = round($score, 2);
    
    if ($due2 >= time()) {
        $answer[] = "staff pregrade: $score / $outof";
    } else {
        # FIXME(cr4bd): Added to fix breakage of histOf()
        #$answer[] = "graded: $score / $outof<br/>(mean: ".round(histOf($name)["score"],2).")";
        $hist = histOf($name);
        if ($hist === FALSE) {
            $answer[] = "graded: $score / $outof<br/>";
        } else {
            $answer[] = "graded: $score / $outof<br/>(mean: ".round($hist["score"],2).")";
        }
    }
    return $answer;
}


?>

<table><thead>
    <tr><th>Quiz</th><th>Title</th><th>Open</th><th>Close</th><th>Time Limit</th><th>Status</th></tr>
</thead><tbody>
<?php
foreach(glob('questions/*.md') as $i=>$name) {
    $name = basename($name,".md");
    $parts = gradeQuiz($name);
    echo '<tr><td><a href="quiz.php?qid='.$name;
    if (array_key_exists('asuser', $_GET)) echo '&asuser='.$user;
    echo '"';
    if ($parts[4] == "available") { 
        echo ' onclick="confirm(\'Really view Quiz '.$name.'?\') || (event.preventDefault() && false)"';
    }
    echo '>'.$name.'</a></td>';
    foreach($parts as $j=>$field) {
        if ($isstaff && (substr($field, 0, 6) == 'graded' || substr($field, 0, 6) == 'failed')) {
            echo "<td>$field<br/><a href='grader.php?qid=$name'>grader site</a></td>";
        } else {
            echo "<td>$field</td>";
        }
    }
    echo "</tr>\n";
}
?>
</tbody></table>
<center><a href="<?= $homepage ?>">Return to course page</a></center>
</body>
</html>
