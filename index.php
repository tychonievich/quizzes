<?php 
require_once "tools.php";
?>
﻿<html>
<head>
    <title><?=$metadata['quizname']?> Index</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <center><a href="<?=$metadata['homepage']?>">Return to course page</a></center>
    <p>
        Welcome, <?=$user?>, to the <?=strtolower($metadata['quizname'])?> index page.
        <?php
        if (file_exists('weights.json')) {
            ?>You may also view the <a href="progress.php">progress tracker</a> if you wish.<?php
        }
        ?>
    </p>
    <p>
        This page is static, meaning its information is based on the time you loaded it.
        To update it you need to reload the page.
    </p>

<style id="delme">body:after { content: "... more quizzes loading ..."; font-size:200%; color: red; }</style>
<table><thead>
    <tr><th>Task</th><th>Title</th><th>Open</th><th>Close</th><th style="text-align:center">Time</th><th style="text-align:center">Status</th></tr>
</thead><tbody>
<?php

$qsortting = array();
foreach(glob('questions/*.md') as $f) {
    $name = basename($f,".md");
    $qobj = qparse($name);
    if (!$isstaff && (
        $qobj['draft'] || 
        ($qobj['hide'] && !file_exists("log/$name/$user.log"))
    )) continue; // unlist
    $qsortting[$qobj['due'].'-'.$name] = array($name, $qobj);
}
ksort($qsortting);
foreach($qsortting as $i=>$qpair) {
    $name = $qpair[0];
    $qobj = $qpair[1];

    if (isset($qobj['error'])) {
        echo "<tr><td colspan='6' class='disconnected'>ERROR parsing $name: <tt>".htmlentities(json_encode($qobj['error']))."</tt></td></tr>";
        continue;
    }
    $sobj = aparse($qobj, $user);
    echo '<tr><td>';
    if ($sobj['may_view']) {
        echo '<a href="quiz.php?qid='.$name;
        if (isset($_GET['asuser'])) echo '&asuser='.$user;
        echo '"';
        if (!$sobj['started'] && $sobj['may_submit']) { 
            echo ' onclick="confirm(\'Really view quiz '.$name.'?\') || (event.preventDefault() && false)"';
        }
        echo ">$name</a></td>";
    } else echo "$name</td>";
    
    echo "<td>$qobj[title]";
    if ($sobj['may_view_key']) echo "<br/><a href='quiz.php?qid=$name&view_only'>view without answers</a>";
    echo "</td>";
    echo "<td>".date('D j M g:ia', $qobj['open'])."</td>";
    echo "<td>".date('D j M g:ia', $qobj['due'])."</td>";
    echo "<td align=center>".durationString($sobj['time_left'])."</td>";

    echo "<td align=center>";
    if (!$sobj['may_view'] || $qobj['open'] > time()) {
        echo "not yet open";
    } else if ($sobj['may_view_key']) {
        $sid = $sobj['slug'];
        $score = grade($qobj, $sid);
        if (!$sobj['started']) echo "did not take";
        else echo round(100*$score,2)."%";
        $hist = histogram($qobj);
        if (isset($hist['total']) && $hist['total'] > 0)
            echo "<br/>(mean: ".round(100*($hist['right']/$hist['total']),2)."%)";
    } else if ($sobj['started'] && $sobj['may_submit']) {
        echo "in progress";
    } else if ($sobj['started']) {
        echo "taken";
    } else if ($sobj['may_submit']) {
        if ($qobj['due'] < time()) echo "late";
        else echo "open";
    } else {
        echo "closed";
    }
    if ($isstaff && $qobj['due'] < time()) {
        echo "<br/><a href='grader.php?qid=$name'>grader site</a>";
    }
    echo "</td>";
    
    echo "</tr>\n";
    flush(); // ob_flush();
}
?>
</tbody></table>
<script>document.getElementById('delme').remove()</script>
<center><a href="<?= $metadata['homepage'] ?>">Return to course page</a></center>
<?php if ($isstaff) { ?>
<p>As course staff, you can see hidden and draft quizzes and <a href="peek.php">look at all student responses</a>.</p>
<?php } ?>
</body>
</html>
