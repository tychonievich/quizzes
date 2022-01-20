<!DOCTYPE html>
<?php 
require_once "tools.php";
?>
<html>
<head>
    <title><?=$metadata['quizname']?> Viewer</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css">
    <?php if (isset($_GET['view_only'])) { ?>
    <style>
        body { background:#fdb; }
        .directions, .multiquestion { background: #fed;  }
    </style>
    <?php
    }
    ?>
    
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

    <script>
<?php if (!isset($_GET['view_only'])) { ?>
function pending(num) {
    if (document.getElementById('q'+num).className != "question submitting")
        document.getElementById('q'+num).className = "question submitting";
}
function postAns(name, num) {
    document.getElementById('q'+num).className = "question submitting";
    var ans = {
        'user':"<?php echo $user; ?>", 
        'quiz':name,
        'slug':document.getElementById("q"+num).getAttribute('slug'), 
        'answer':[]
    };
    var elems = document.getElementsByName("ans"+num);
    for(var i=0; i<elems.length; i+=1) {
        var elem = elems[i];
        if (elem.type == 'text' || elem.type == 'textarea')
            elem.value = elem.value.trim();
        if (elem.type == 'text' || elem.type == 'textarea' || elem.checked) { ans.answer.push(elem.value); }
    }
    var comm = document.getElementById('comments'+num);
    console.log(comm)
    if (comm && comm.value) {
        comm.value = comm.value.trim();
        ans['comments'] = comm.value;
    }
    
    
    console.log("sending: ", JSON.stringify(ans));
    ajaxSend(JSON.stringify(ans), num);

}
function ajaxSend(data, num) {
    var xhr = new XMLHttpRequest();
    if (!("withCredentials" in xhr)) {
        return null;
    }
    xhr.open("POST", "quiz_listener.php<?php
    if (isset($_GET['asuser'])) echo '?asuser='.$_GET['asuser'];
    ?>", true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("Content-type", 'application/json');
    xhr.onerror = function() {
        document.getElementById("notice").innerHTML = "auto-check for new data broken";
    }
    xhr.onreadystatechange = function() { 
        if(xhr.readyState == 4) {
            if (xhr.status == 200) {
                document.getElementById('q'+num).className = "question submitted";
                console.log("response: " + xhr.responseText);
            } else {
                document.getElementById('q'+num).className = "question disconnected";
                alert("Your latest response failed to arrive at the server.\nThis could be your Netbadge session expiring, or because of the following error message:\n\n"   + JSON.stringify(xhr.responseText));
                console.log("response: " + xhr.responseText);
            }
        } else {
            document.getElementById('q'+num).className = "question disconnected";
            // alert("Your latest response failed to arrive at the server.\n  This is probably due to Netbadge disconnecting you behind the scenes.\n  We recommend you note any answers that have not turned green, reload the page, and enter them again.\n\nCode: " + xhr.status+"\nDetails: "+xhr.responseText)
            // console.log("response: " + xhr.responseText);
        }
    }
    xhr.send(data);
}
var due = Date.now();
var timer;
function tick() {
    var clock = document.getElementById('clock');
    var remaining = due - Date.now();
    if (remaining < 0) {
        clock.innerHTML = "Time is up; further changes will be ignored";
        clearInterval(timer);
    } else {
        remaining /= 1000; remaining = Math.floor(remaining); // milliseconds -> seconds;
        var seconds = remaining % 60;
        remaining /= 60; remaining = Math.floor(remaining); // seconds -> minutes;
        var minutes = remaining % 60;
        remaining /= 60; remaining = Math.floor(remaining); // minutes -> hours;
        var hours = remaining;
        var text = "Time remaining: ";
        text += hours + ":";
        text += (minutes < 10 ? "0"+minutes : minutes)+":";
        text += (seconds < 10 ? "0"+seconds : seconds)+"";
        clock.innerHTML = text;
    }
}
<?php } // endif !isset($_GET['view_only']) ?>
function onload() {
    if (document.getElementById('clock')) {
        var remaining = Number(document.getElementById('clock').innerHTML);
        due += remaining * 1000;
        timer = setInterval(tick, 1000);
    }
}

    </script>
</head>
<body onload="onload()">
    <a style="text-align:center; display:block;" href="<?= $metadata['homepage'] ?>">Return to course page</a><a href="<?php
if (isset($_GET['asuser'])) echo '.?asuser='.$user;
else echo '.';
?>" style="text-align:center; display:block;">Return to index</a><?php

function imgup() {
    global $user, $realisstaff, $metadata;
    if (isset($_POST['rot'])) { // image rotation request
        $slug = $_REQUEST['slug'];
        echo '<pre>';
        if (strpos($slug,"/") !== FALSE || strpos($slug,"-") !== FALSE
        || !file_exists("log/$_GET[qid]/$user-$slug")) {
            echo "ERROR: malformed request";
        } else {
            $finfo = new finfo(FILEINFO_MIME);
            $dir = "log/$_GET[qid]";
            $img = "$dir/$user-$slug";
            $mime = $finfo->file($img);
            $stdout = array();
            $retval = 0;
            $tmp = tempnam($dir,"imgrot");
            unlink($tmp);

            $rot = 0;
            switch($_POST['rot']) {
                case '⊤': break; // no action needed
                case '⊣': $rot += 90;
                case '⊥': $rot += 90;
                case '⊢': $rot += 90;

                putlog("$_GET[qid]/$user.log", '{"date":"'.date('Y-m-d H:i:s').'","rotation":'.$rot.'}'."\n");
                if ($mime == 'image/jpeg') {
                    exec("jpegtran -rotate $rot -outfile $img $img", $stdout, $retval);
                } else if ($mime == 'image/png') {
                    exec("gm convert $img -rotate $rot $tmp.png", $stdout, $retval);
                    if (!$retval) $retval = !rename("$tmp.png", $img);
                } else {
                    exec("gm convert $img -rotate $rot $tmp.jpg", $stdout, $retval);
                    if (!$retval) $retval = !rename("$tmp.jpg", $img);
                }
            }
            if ($retval)
                echo "ERROR: rotation failed $retval\n  ".implode("\n  ",$stdout);
            else
                echo "Rotated image";
            if (file_exists("$tmp.jpg")) unlink("$tmp.jpg");
            if (file_exists("$tmp.png")) unlink("$tmp.png");
        }
        echo '</pre>';
    }
    if (count($_FILES) > 0) {
        if (!$_GET['qid'] || strpos($_GET['qid'],"/") !== FALSE) {
            echo "<pre>ERROR: malformed request</pre>";
            return;
        }

        $qobj = qparse($_GET['qid']);
        if (isset($qobj['error'])) { 
            echo "<pre>ERROR: $qobj[error]</pre>";
            return;
        }
        if (!$realisstaff) {
            $sobj = aparse($qobj, $user);
            if (!$sobj['may_submit']) {
                if (FALSE && isset($metadata['upload-leeway']) && $sobj['time_left'] > -60*$metadata['upload-leeway']) {
                    $mins = ceil(-$sobj['time_left']/60);
                    echo "<pre>INFO: your upload arrived $mins minutes late</pre>";
                } else {
                    echo "<pre>ERROR: You may not upload to this quiz at this time</pre>";
                    return;
                }
            }
        }

        umask(0); // discouraged but important for mkdir

        $dir = "log/".$_GET['qid'];
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        echo '<pre>';
        foreach($_FILES as $slug=>$fdet) {
            if (strpos($slug,"/") !== FALSE || strpos($slug,"-") !== FALSE) {
                echo "ERROR: malformed request";
                continue;
            }
            $name = $fdet['name'];
            $error = $fdet['error'];
            $tmp = $fdet['tmp_name'];
            if ($error == UPLOAD_ERR_INI_SIZE) {
                echo "ERROR: $name was a larger file than the server can accept";
                continue;
            }
            if ($error == UPLOAD_ERR_FORM_SIZE) {
                echo "ERROR: $name did not upload because of impossible error number 0";
                continue;
            }
            if ($error == UPLOAD_ERR_PARTIAL) {
                echo "ERROR: only part of $name was received; please try again.";
                continue;
            }
            if ($error == UPLOAD_ERR_NO_FILE) {
                echo "ERROR: your browser said it would upload $name, but did not include a file.";
                continue;
            }
            if ($error > UPLOAD_ERR_NO_FILE) {
                echo "ERROR: $name did not uplaod because of impossible error number ".$error;
                continue;
            }
            if (filesize($tmp) <= 0) {
                echo "ERROR: $name arrived as an empty file.";
                continue;
            }
            
            putlog("$_GET[qid]/$user.log", json_encode(array(
                "date"=>date('Y-m-d H:i:s'),
                "upload-to" => $slug,
                "upload-from" => $name
            ))."\n");
                
            if (stripos($name, ".HEIC") || stripos($name, ".HEIF")) {
                $stdout = array();
                $retval = 0;
                exec("heif-convert $tmp $dir/$user-$slug.jpg", $stdout, $retval);
                if ($retval) {
                    echo "Failed to convert HEIC file into a usable format. Please try again with a JPEG or PNG";
                } else {
                    rename("$dir/$user-$slug.jpg", "$dir/$user-$slug");
                    chmod("$dir/$user-$slug", 0666);
                    echo "Received $name and converted to JPEG";
                }

            } else if (stripos($name, ".PDF")) {
                $stdout = array();
                $retval = 0;
                exec("gm convert -density 200 $tmp -trim -append +repage $dir/$user-$slug.jpg", $stdout, $retval);
                if ($retval) {
                    echo "Failed to convert PDF file into a usable format. Please try again with a JPEG or PNG";
                } else {
                    rename("$dir/$user-$slug.jpg", "$dir/$user-$slug");
                    chmod("$dir/$user-$slug", 0666);
                    echo "Received $name and converted to JPEG";
                }
            } else if (strpos($fdet['type'], 'image/') === 0 
            || stripos($name, ".JPG") || stripos($name, ".JPEG")
            || stripos($name, ".PNG")) {
                if (move_uploaded_file($tmp, "$dir/$user-$slug")) {
                    chmod("$dir/$user-$slug", 0666);
                    echo "Received $name";
                } else {
                    echo "ERROR: server failed to save $name";
                }
            } else {
                echo "ERROR: Unable to process $name. Please upload an image file.";
            }
        }
        echo '</pre>';
    }
}

function newRegrade($qid) {
    global $user, $realistaff;

    if (!isset($_POST['regrade']) || !isset($_POST['request']) || !$_POST['request']) return; // no request sent

    $qobj = qparse($qid);
    if (isset($qobj['error'])) { echo "<pre>Regrade error: $qobj[error]</pre>"; return; }
    if (!$qobj['regrades']) { echo "<pre>Regrade error: this page is not accepting regrade requests</pre>"; return; }
    
    $q = null;
    foreach($qobj['q'] as $mq) foreach($mq['q'] as $_q)
        if ($_q['slug'] == $_POST['regrade']) $q = $_q;
    if ($q === null) { echo "<pre>Regrade error: question does not exist</pre>"; return; }
    
    // always post to log
    putlog("$qid/$user.log", json_encode(array(
        'slug'=>$_POST['regrade'],
        'request'=>$_POST['request'],
        'date'=>date('Y-m-d H:i:s'),
    ))."\n");

    putlog("$qid/regrades.log", json_encode(array(
        'student'=>$user,
        'task'=>$_POST['regrade'],
        'add'=>true,
        'date'=>date('Y-m-d H:i:s'),
    ))."\n");

    // if a rubric, also post a rubric clear action
    if (file_exists("log/$qid/gradelog_$_POST[regrade].lines"))
        putlog("$qid/gradelog_$_POST[regrade].lines", "$user\tnull\t\"\"\t".date('Y-m-d H:i:s')."\t$_SERVER[PHP_AUTH_USER]\n");
    
    echo "<pre>Regrade request recorded. It may take a week or two for a response.</pre>"; return; 
}

function showQuiz($qid, $blank = false) {
    global $user, $metadata, $isstaff, $realisstaff;
    $qobj = qparse($qid);
    if (isset($qobj['error'])) { echo $qobj['error']; return; }

    echo "<h1 style='text-align:center'>$qobj[title]</h1>";
    
    $sobj = aparse($qobj, $user);
    if (!$sobj['may_view']) { echo "You may not view this quiz"; return; }

    if ($isstaff && isset($_GET['showkey'])) {
        $sobj['may_view_key'] = true;
        echo "<center class='count'>".count(glob("log/$qid/*.log"))." people have viewed this quiz</center>";
    }
    
    
    if ($sobj['may_submit'] && !$sobj['started'] && ($user == $_SERVER['PHP_AUTH_USER']))
        putLog("$qid/$user.log", '{"date":"'.date('Y-m-d H:i:s').'"}'."\n");
    if ($sobj['may_submit'])
        echo "<div id='clock'>$sobj[time_left]</div>";

    $hist = (!$blank && $sobj['may_view_key']) ? histogram($qobj) : false;
    if ($hist) grade($qobj, $sobj); // annotate with score

   
    echo "<div class='directions'>$qobj[directions]</div>";
    
    if ($qobj['qorder'] == 'shuffle' && $hist === false && !$isstaff) {
        srand(crc32("$user $qobj[slug]"));
        shuffle($qobj['q']);
    }

    if (isset($qobj['external'])) {
        echo "<div class='question'>You earned ".($sobj['score']*100)."% on this assignment</div>";
    }

    $qnum = 0;
    foreach($qobj['q'] as $qg) {

        if ($qobj['qorder'] == 'shuffle' && $hist === false && !$isstaff)
            shuffle($qg['q']);

        if (count($qg['q']) > 1 || $qg['text'])
            echo '<div class="multiquestion">';
        if ($qg['text']) echo $qg['text'];
        foreach($qg['q'] as $q) {
            $qnum += 1;
            
            showQuestion($q, $qid, $qnum, $user, $qobj['comments']
                ,$qg['text']
                ,(!$blank && isset($sobj[$q['slug']]))
                    ? $sobj[$q['slug']]
                    : array('answer'=>array(),'comments'=>'')
                ,(!$sobj['may_submit'] && !$blank)
                ,$hist
                ,!$blank
                ,$isstaff || $hist !== false
                ,$qobj['regrades']
                );
        }
        if (count($qg['q']) > 1 || $qg['text']) echo '</div>';
    }
    if ($isstaff && !$hist)
        echo "<div class='explanation'><a href='$_SERVER[REQUEST_URI]&showkey'>click here to preview key</a></div>";
    
    if ($realisstaff) {
        echo "<form action='$_SERVER[REQUEST_URI]' method='GET'><input type='text' list='students-list' name='asuser'/><datalist id='students-list'>";
        foreach(glob("log/$qobj[slug]/*.log") as $path) {
            $u = basename($path, ".log");
            echo "<option value='$u'>$u</option>";
        }
        echo "</datalist><input type='hidden' name='qid' value='$qobj[slug]'/><input type='submit' value='view as student'/></form>";
        
        if (file_exists("log/$qid/.$user.log")) {
            echo "<div>An archived submission for $user has been removed from grading; <a href='?qid=$qid&asuser=$user&restore=$user'>restore that submission</a></div>";
        } else if (file_exists("log/$qid/$user.log")) {
            $tmp = aparse("$qid","$user")['start'];
            echo "<div>$user did open this quiz, first viewing it at ";
            echo date('Y-m-d H:i:s', $tmp);
            echo"; <a href='?qid=$qid&asuser=$user&archive=$user'>archive it and remove its grade</a></div>";
        } else {
            echo "<div>$user did not submit this quiz.</div>";
        }
    }

}

function handleArchive() {
    global $realisstaff;
    if (!$realisstaff) return;
    if (!isset($_GET['qid'])) {
        echo "<pre>No quiz selected</pre>";
        return;
    }
    $qid = $_GET['qid'];
    if (isset($_GET['archive'])) {
        $user = $_GET['archive'];
        if (file_exists("log/$qid/$user.log") && !file_exists("log/$qid/.$user.log")) {
            rename("log/$qid/$user.log", "log/$qid/.$user.log");
            foreach(glob("log/$qid/$user-*") as $img)
                rename($img, "log/$qid/.".basename($img));
            echo "<pre>Archived $user's $qid</pre>";
        } else {
            echo "<pre>Failed to archive $user's $qid</pre>";
        }
    } else if (isset($_GET['restore'])) {
        $user = $_GET['restore'];
        if (file_exists("log/$qid/.$user.log") && !file_exists("log/$qid/$user.log")) {
            rename("log/$qid/.$user.log", "log/$qid/$user.log");
            foreach(glob("log/$qid/.$user-*") as $img)
                rename($img, "log/$qid/".substr(basename($img),1));
            echo "<pre>Restored $user's previously-archived $qid</pre>";
        } else {
            echo "<pre>Failed to restore $user's $qid</pre>";
        }
    }
}

handleArchive();
imgup();
newRegrade($_GET['qid']);
showQuiz($_GET['qid'], isset($_GET['view_only']));

?>
<a style="text-align:center; display:block;" href="<?= $metadata['homepage'] ?>">Return to course page</a></center><a href="<?php
if (isset($_GET['asuser'])) echo '.?asuser='.$user;
else echo '.';
?>" style="text-align:center; display:block;">Return to index</a>
<!--<center><a href="../">Return to course page</a></center>-->
</body>
</html>
