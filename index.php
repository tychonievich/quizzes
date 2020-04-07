<?php 
require_once "tools.php";
?>
﻿<html>
<head>
    <title><?=$metadata['quizname']?> Index</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <center><a href="<?=$metadata['homepage']?>">Return to course page</a></center>
    <p>
        Welcome, <?=$user?>, to the <?=strtolower($metadata['quizname'])?> index page.
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
foreach(glob('questions/*.md') as $i=>$name) {
    $name = basename($name,".md");
    $qobj = qparse($name);
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
    if (!$sobj['may_view']) {
        echo "not yet open";
    } else if ($sobj['may_view_key']) {
        $score = grade($qobj, $sobj);
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
</body>
</html>
