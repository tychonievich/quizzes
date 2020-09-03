<?php

require_once "tools.php";
 if (!$isstaff) {
    http_response_code(403);
    die('staff use only');
}

?><!DOCTYPE html><html>
    <head>
    <title>View <?=$metadata['quizname']?> <?=isset($_GET['qid']) ? $_GET['qid'] : ''?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="katex/katex.min.js"></script>
    <link rel="stylesheet" href="katex/katex.min.css">
    <script type="text/javascript" src="columnsort.js"></script>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll('span.mymath').forEach(x => 
                katex.render(x.innerText, x, 
                    {throwOnError:false, displayMode:false})
            )
            document.querySelectorAll('div.mymath').forEach(x => 
                katex.render(x.innerText, x, 
                    {throwOnError:false, displayMode:true})
            )
        });
    </script><style type="text/css">
        div.answer p:first-child { margin-top: 0; }
        div.answer p:last-child { margin-bottom: 0; }
    </style>
    </head>
<body onload="hookAllTables()"><?php 

// list quizzes, questions, or answers
if (!isset($_GET['qid']) || isset(($qobj = qparse($_GET['qid']))['error'])) {
    // list quizzes
    foreach(glob('questions/*.md') as $i=>$name) {
        $name = basename($name,".md");
        $qobj = qparse($name);
        if ($sobj['due'] >= time()) continue;
        echo "<br/><a href='?qid=$name'>$name: $qobj[title]</a>";
    }
} else {
    // list questions or answers
    $questions = array();
    $mqs = array();
    foreach($qobj['q'] as $mq) foreach($mq['q'] as $q) {
        $questions[$q['slug']] = $q;
        $mqs[$q['slug']] = $mq;
    }
    if (isset($_GET['slug']) && isset($questions[$_GET['slug']])) {
        // list answers
        $slug = $_GET['slug'];
        $q = $questions[$slug];
        if ($mqs[$slug]['text']) {
            echo '<div class="multiquestion">';
            echo $mqs[$slug]['text'];
        }
        echo '<div class="question">';
        echo $q['text'];
        echo '</div>';
        if ($mqs[$slug]['text']) echo '</div>';

        $slug2html = array();
        if (isset($q['options']))
            foreach($q['options'] as $opt)
                $slug2html[$opt['slug']] = $opt['text'];

        
        ?><table><thead><tr><th>User</th><th>Answer</th><th>Comment</th></tr></thead>
        <tbody><?php
        
        foreach(glob("log/$_GET[qid]/*.log") as $path) {
            $user=basename($path, '.log');
            $a = aparse($qobj, $user);
            if (!isset($a[$slug])) continue;
            
            echo "<tr><td>$user</td><td>";
            foreach($a[$slug]['answer'] as $ans) {
                echo '<div class="answer">';
                if (isset($slug2html[$ans])) echo $slug2html[$ans];
                else echo htmlentities($ans);
                echo '</div>';
            }
            echo "</td><td>";
            echo '<div class="comments">';
            echo htmlentities($a[$slug]['comments']);
            echo '</div>';
            echo "</td></tr>";
        }
        
        ?></tbody></table><?php
        
    } else {
        ?><style type="text/css">
        a { text-decoration: none !important; color:inherit !important; }
        </style><?php
        // list questions
        foreach($questions as $slug=>$q) {
            echo "<a href='?qid=$_GET[qid]&slug=$slug'>";
            if ($mqs[$slug]['text']) {
                echo '<div class="multiquestion">';
                echo $mqs[$slug]['text'];
            }
            echo '<div class="question">';
            echo $q['text'];
            echo '</div>';
            if ($mqs[$slug]['text']) echo '</div>';
            echo "</a>";
        }
    }
    
}



?></body></html>
