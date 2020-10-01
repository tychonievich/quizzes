<!DOCTYPE html>
<html><head><title>Progress in course</title></head>
<body>
<?php
if ($_SERVER['PHP_AUTH_USER'] != 'lat7h') {
    die("This page is not yet fonished.");
}
require_once "tools.php";

$outline = json_decode(file_get_contents('weights.json'), true);
$outline['name'] = 'CS 2102';

/**
 * return the score the student earned on the given task.
 * Also sets &$status to one of
 * 'taken', 'future', 'missed'
 */
function oneScore($task, &$status=FALSE) {
    global $user;
    $qobj = qparse($task);
    if (!$qobj || isset($qobj['error']) || $qobj['keyless'] || $qobj['draft'] || $qobj['due'] > time()) $status = 'future';
    else if (!file_exists("log/$task/$user.log")) $status = 'missed';
    else {
        $status = 'taken';
        $tmp = $user;
        return grade($qobj, $tmp);
    }
    return 0;
}

function annotate(&$outline) {
    if (is_string($outline)) {
        $stat = FALSE;
        $ans = array(
            "name" => $outline,
            "earned" => oneScore($outline, $stat),
        );
        $ans['status'] = $stat;
        $outline = $ans;
        return;
    }
    switch($outline['type']) {

    case 'groups': {
        $got = 0;
        $of = 0;
        $opened = FALSE;
        $closed = TRUE;
        foreach($outline['parts'] as &$obj) {
            $weight = isset($obj['weight']) ? $obj['weight'] : 1;
            annotate($obj);
            if ($obj['status'] == 'future') {
                $closed = FALSE;
            } else {
                $opened = TRUE;
                $got += $weight*$obj['earned'];
                $of += $weight;
            }
        }
        $outline['earned'] = $of ? $got/$of : 0;
        $outline['status'] = $closed ? 'taken' : ($open ? 'open' : 'future');
    } break;

    case 'replace': {
        $outline['status'] = 'future';
        $outline['earned'] = 0;
        foreach($outline['parts'] as &$obj) {
            annotate($obj);
            if ($obj['status'] == 'taken' && $obj['earned']) {
                $outline['status'] = 'taken';
                $outline['earned'] = $obj['earned'];
            }
        }
    } break;

    case 'best': {
        if (isset($outline['keep'])) {
            $outline['status'] = 'not configured '.__LINE__;
        } else if (isset($outline['drop'])) {
            $outline['status'] = 'not configured '.__LINE__;
        } else {
            $outline['status'] = 'future';
            $outline['earned'] = 0;
            foreach($outline['parts'] as &$obj) {
                annotate($obj);
                if ($obj['status'] == 'taken' && $obj['earned'] > $outline['earned']) {
                    $outline['status'] = 'taken';
                    $outline['earned'] = $obj['earned'];
                }
            }
        }
    } break;

    default:
    $outline['status'] = 'not configured '.__LINE__;
    }
}

function display($outline, $depth=0) {
    if ($depth == 0) {
        ?><table style="border-collapse:collapse"><thead><tr><th>Task</th><th>Weight</th><th>Status</th><th>Score</th></tr></thead><tbody><?php
    }
    echo "<tr style='background: rgba(0,0,0,".(0.25/(1+$depth)).")'>";
    echo "<td style='font-size:".(500/(3+$depth))."%; padding-left:${depth}em'>$outline[name]</td>";
    echo "<td style='text-align:center'>".(isset($outline['weight']) ? $outline['weight']: '')."</td>";
    echo "<td style='text-align:center'>".($outline['status'] == 'future' && $outline['earned'] ? 'in progress' : ($outline['status'] == 'future' ? '' : $outline['status']))."</td>";
    echo "<td style='text-align:right'>".($outline['earned'] || $outline['status'] != 'future' ? (100*$outline['earned']).'%' : '')."</td>";

    echo "</tr>";

    if (isset($outline['parts']) && count($outline['parts']) > 1) {
        foreach($outline['parts'] as $part) {
            display($part, $depth+1);
        }
    }
    
    if ($depth == 0) {
        ?></tbody></table><?php
    }
}

function ownScore() {
    global $outline;
    annotate($outline);
    display($outline);
}

function allScores() {
    global $user, $outline;
    $section = (isset($_GET['section'])) ? $_GET['section'] : FALSE;
    $smap = json_decode(file_get_contents('sections.json'), true);
    $olduser = $user;
    echo "<table><thead><tr><th>User</th><th>Grade</th></thead><tbody>";
    foreach($smap as $cid => $sec) {
        if ($section && $sec != $section) continue;
        $user = $cid;
        $score = $outline;
        annotate($score);
        $user = $olduser;
        echo "<tr><td><a href='?asuser=$cid' target='_blank'>$cid</a></td><td>".(100*$score['earned'])."</td></tr>";
    }
    echo "</tbody></table>";
    echo "<script type='text/javascript' src='columnsort.js'></script>";
    echo "<script type='text/javascript'>hookAllTables()</script>";
}

if ($isstaff) {
    allScores();
} else {
    ownScore();
}

?>
</body>
</html>
