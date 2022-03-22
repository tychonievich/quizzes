<!DOCTYPE html>
<?php 
require_once "tools.php";
if (!$isstaff) die("Staff only"); // IMPORTANT! This code does not check may_view so must black non-staff users
?>
<html>
<head>
    <title><?=$metadata['quizname']?> Printer</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css">
    <style>
        html { font-size: 10pt; }
        body { columns: 2; }
        body, .directions, .multiquestion { background:#fff; }
        input[type="text"] { background: none; border: none; border-bottom: thin solid black; width: 3in; padding-top: 1em; }
        ol.options > li { list-style: none; }
        .label { display: float; float: left; clear: both; padding-right: 1ex; }
        strong + ol.options { margin-top: 0.5ex; }
        ol.options { margin-left: -1.5em; }
        .question { page-break-inside: avoid; margin-left: 0em; }
        .multiquestion { page-break-inside: avoid; }
        .multiquestion > .question { margin-bottom: 0em; margin-left: 1em; }
        p { margin: 0; }
        p + p { text-indent: 1.5em; }
        .multiquestion > .question { display: inline-block; vertical-align: top; }
        .directions,.counts { padding: 1ex 0; margin: 0; border-bottom: thin dotted black; }
        .box > span { display:none; }
        .box > textarea { width: 100%; background: #fff; border: thin solid black; }
    </style>
    
    <link rel="stylesheet" href="katex/katex.min.css">
    <script type="text/javascript" src="katex/katex.min.js"></script>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('span.mymath').forEach(x => 
                katex.render(x.textContent, x, 
                    {throwOnError:false, displayMode:false})
            )
            document.querySelectorAll('div.mymath').forEach(x => 
                katex.render(x.textContent, x, 
                    {throwOnError:false, displayMode:true})
            )
        });
    </script>

</head>
<body>
<?php

function showQuiz($qid, $seed = false) {
    global $user;
    $qobj = qparse($qid);
    if (isset($qobj['error'])) { echo $qobj['error']; return; }
    
    $title = $seed ? "$qobj[title] (v.$seed)" : $qobj['title'];

    echo "<h1 style='text-align:center'>$title</h1>";
    echo "<script>document.title = '$title'</script>";
    
    echo "<p>Name: <input type='text' style='width:3in'>";
    echo "<br/>Computing ID: <input type='text' style='width:1in'></p>";

    $num_questions = 0;
    $num_parts = 0;
    $num_points = 0;
    foreach($qobj['q'] as $qg) {
        $num_questions += 1;
        foreach($qg['q'] as $q) {
            $num_parts += 1;
            $num_points += $q['points'];
        }
    }

    echo "<div class='directions'>$qobj[directions]</div><div class='counts'>";
    if ($num_parts == $num_questions) echo "<p>There are $num_parts questions ";
    else echo "<p>There are $num_questions questions with $num_parts parts ";
    echo "worth $num_points total points.</p>";
    echo "</div>";



    if ($qobj['qorder'] == 'shuffle' && $seed) {
        srand(crc32("$seed $qobj[slug]"));
        shuffle($qobj['q']);
    }

    $qnum = 0;
    foreach($qobj['q'] as $qg) {

        if ($qobj['qorder'] == 'shuffle' && $seed)
            shuffle($qg['q']);

        $multi = count($qg['q']) > 1;

        $qnum += 1;
        if ($multi) echo "<div class='multiquestion'><strong>Question $qnum</strong>";
        else echo "<div class='question'><strong>Question $qnum</strong>";
        if ($qg['text']) echo $qg['text'];

        $pnum = 0;
        foreach($qg['q'] as $q) {
            $pnum += 1;
            showQuestion($q, $qid, ($multi ? $qnum.'.'.chr($pnum+96) : false)
                ,$seed // use seed as the user for shuffling
                ,false // no comments boxes
                ,$qg['text']
                ,array('answer'=>array(),'comments'=>'') // no replies
                ,true // disable inputs
                ,false // no histogram
                ,false // no ajax
                ,!$seed // unshuffle?
                ,false // no regrade box
                ,true // print mode enabled
                );
        }
        echo "</div>";
    }

}

showQuiz($_GET['qid'], $_GET['seed']);

?>
<script>
document.querySelectorAll('.label').forEach(x=>x.nextElementSibling.prepend(x))
document.querySelectorAll('.label').forEach(x=>x.nextElementSibling.prepend(x))
</script>
<div class="multiquestion"><strong>Pledge:</strong>
<p>On my honor, I pledge that I have neither given nor received help on this assignment.</p>
Signature: <input type="text"/>


<!--<center><a href="../">Return to course page</a></center>-->
</body>
</html>
