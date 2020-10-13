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

        if (file_exists('log/names.json')) {
            $users = json_decode(file_get_contents('log/names.json'),true);
        } else {
            $users = array();
        }

        ?><table><thead><tr><th>User</th><th>Answer</th><th>Comment</th></tr></thead>
        <tbody><?php

        foreach(glob("log/$_GET[qid]/*.log") as $path) {
            $user=basename($path, '.log');
            $a = aparse($qobj, $user);
            if (!isset($a[$slug]) && !file_exists("log/$_GET[qid]/$user-$slug")) continue;

            echo "<tr><td>$user";
            if (isset($users[$user])) {
                echo " – ";
                echo $users[$user];
            }
            echo "</td><td>";
            if (isset($a[$slug])) 
                foreach($a[$slug]['answer'] as $ans) {
                    echo '<div class="answer">';
                    if (isset($slug2html[$ans])) echo $slug2html[$ans];
                    else echo htmlentities($ans);
                    echo '</div>';
                }
            if (file_exists("log/$_GET[qid]/$user-$slug")) {
                echo "<div style='display:inline-block; font-size:2em;'><a onclick='spin(\"img-$user\",-90)'>↶</a><br/><a onclick='spin(\"img-$user\",90)'>↷</a></div>";
                echo "<div id='img-$user' style='display:inline-block'>";
                echo "<img class='preview' src='imgshow.php?asuser=$user&qid=$_GET[qid]&slug=$q[slug]'/>";
                echo "</div>";
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
            echo "</a> ";
        }
    }

}

?>
<script type="text/javascript">
/// CSS transforms don't impact bounding boxes, so this depends on
/// a wrapper around the item to rotate to fix that.
function setRot(deg, bucket, img) {
    let h = img.clientHeight,
        w = img.clientWidth,
        m = Math.min(w,h),
        M = Math.max(w,h);
    if (deg%180 == 0) {
        bucket.style.width = 'auto';
        bucket.style.height = 'auto';
    } else {
        bucket.style.width = h+'px';
        bucket.style.height = w+'px';
    }
    img.style.transform = 'rotate('+deg+'deg)';
    if (deg == 90 || deg == -270)
        img.style.transformOrigin = (h/2)+'px '+(h/2)+'px'
    if (deg == 180 || deg == -180)
        img.style.transformOrigin = 'center'
    if (deg == 270 || deg == -90)
        img.style.transformOrigin = (w/2)+'px '+(w/2)+'px'
}
/// dir should be ±90 -- +90 for CW, -90 for CCW
function spin(id, dir) {
    let e = document.getElementById(id);
    let i = e.firstElementChild;
    let r = e.getAttribute('rot') || 0
    r = ((0|r)+dir)%360;
    setRot(r, e, i);
    e.setAttribute('rot', r);
}
</script>
</body></html>
