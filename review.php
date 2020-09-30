<!DOCTYPE html>
<?php 
require_once "tools.php";
?>
<html>
<head>
    <title>Practice Quizzes</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        body { background:#fdb; }
        .directions, .multiquestion { background: #fed;  }
    </style>
    
    <link rel="stylesheet" href="katex/katex.min.css">
    <script type="text/javascript" src="katex/katex.min.js"></script>
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
    </script>
</head>
<body>
    <a style="text-align:center; display:block;" href="<?= $metadata['homepage'] ?>">Return to course page</a>
<?php

if ($_GET['qid'] && strpos($_GET['qid'], '/') === FALSE  && strpos($_GET['qid'], '.') === FALSE && file_exists("review/$_GET[qid].md")) {
    $qobj = qparse("review/$_GET[qid].md", TRUE);

    echo "<h1 style='text-align:center'>$qobj[title]</h1>";
    echo "<div class='directions'>$qobj[directions]</div>";

    $qnum = 0;
    foreach($qobj['q'] as $qg) {
        if (count($qg['q']) > 1 || $qg['text']) echo '<div class="multiquestion">';
        if ($qg['text']) echo $qg['text'];

        foreach($qg['q'] as $q) {
            if (!$q['points']) continue;
            $qnum += 1;
            
            echo "<div class='question'>";
            
            echo "<div class='description'>";
            echo "<strong>Question $qnum</strong>";
            if (isset($qg['text']) && $qg['text']) echo " (see above)";
            echo "\n$q[text]";
            echo "</div>";

            if ($q['type'] == 'radio' || $q['type'] == 'checkbox') {
                echo '<ol class="options">';
                foreach($q['options'] as $opt) {
                    if ($opt['hide']) continue;
                    if (!$opt['points'] && $q['type'] == 'checkbox') continue;
                    echo "<li><label>";
                    echo "<input type='$q[type]' name='ans$qnum'/>";
                    echo "<div>$opt[text]</div>";
                    echo "</label>";
                    if (isset($opt['explain']))
                        echo "<details><summary>Explanation</summary>$opt[explain]</details>";
                    echo "</li>";
                }
                echo '</ol>';
                echo "<details><summary>Key:</summary>";
                $n = 0;
                foreach($q['options'] as $opt) {
                    if ($opt['hide']) continue;
                    if (!$opt['points'] && $q['type'] == 'checkbox') continue;
                    $n += 1;
                    if ($opt['points'] >= 1 || ($opt['points'] > 0 && $q['type'] == 'checkbox')) echo " ".chr($n+0x40);
                }
                "</details>";
            } else if ($q['type'] == 'box' || $q['type'] == 'img') {
                echo "<div class='tinput'><span>Answer:</span><textarea></textarea></div>";
            } else if ($q['type'] == 'text') {
                echo "<div class='tinput'><span>Answer:</span><input type='text'/></div>";
            }
            if (isset($q['key'][0]['text'])) echo "<details><summary>Key:</summary><tt>".htmlentities($q['key'][0]['text'])."</tt></details>";
            if (isset($q['explain'])) echo "<details><summary>Explanation:</summary><tt>$q[explain]</tt></details>";
            
            echo "</div>";
        }

        if (count($qg['q']) > 1 || $qg['text']) echo '</div>';
    }

} else {
    $tmp = array();
    foreach(glob('review/*.md') as $f) {
        $q = qparse($f, TRUE);
        
        if (!$isstaff && ($q['draft'] || ($q['hide']))) continue; // unlist

        $s = basename($f,'.md');
        $tmp[$q['due'].'-'.$s] = array($s, $q['title']);
    }
    ksort($tmp);
    echo "<ul>\n";
    foreach($tmp as $val)
        echo "  <li><a href='review.php?qid=$val[0]'>Practice $val[0]</a>: $val[1]</li>\n";
    echo "</ul>";
}

?>
<a style="text-align:center; display:block;" href="<?= $metadata['homepage'] ?>">Return to course page</a></center>
</body>
</html>
