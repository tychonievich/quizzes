<?php 
require_once "staff.php";
?>
﻿<html>
<head>
    <title>Quiz Viewer</title>
    <style>
        ol.options { margin-top:-1em; }
        ol.options li p { display: inline; }
        ol.options li { list-style: upper-alpha; }
        body { background:#ccc; padding-top:1em; }
        .question { background: white; border-radius:1ex; padding:0ex 1ex; margin:2ex;  }
        .directions { background: #eee; padding:1ex 1ex; margin:2ex;  }
        .multiquestion { background: #eee; border-radius:1ex; padding:0.7ex 0.7ex; }
        .multiquestion + .multiquestion { margin-top:1em; }
        .submitting { background: #fe7; }
        .submitted { background: #dfd; }
        textarea { background-color: inherit; }
        #clock { position:fixed; right:0px; top:0px; background:#ff7; padding-left:0.5ex; padding-bottom:0.5ex; border-bottom-left-radius:1ex; border-left: 1px solid black; border-bottom: 1px solid black;}
        .correct { background-color: #bfb; padding: 0ex 1ex; }
        .incorrect { background-color: #fbb; padding: 0ex 1ex; }
        .hist { color:#777; min-width: 2em; text-align:right; display:inline-block; }
    </style>
    <script>
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
        if (elem.type == 'text' || elem.checked) { ans.answer.push(elem.value); }
    }
    var comm = document.getElementById('comments'+num);
    if (comm && comm.value) ans['comments'] = comm.value;
    
    
    console.log("sending: ", JSON.stringify(ans));
    ajaxSend(JSON.stringify(ans), num);

}
function ajaxSend(data, num) {
    var xhr = new XMLHttpRequest();
    if (!("withCredentials" in xhr)) {
        return null;
    }
    xhr.open("POST", "report.php", true);
    xhr.withCredentials = true;
    xhr.setRequestHeader("Content-type", 'application/json');
//    xhr.setRequestHeader("Content-length", data.length);
    xhr.onerror = function() {
        document.getElementById("notice").innerHTML = "auto-check for new data broken";
    }
    xhr.onreadystatechange = function() { 
        if(xhr.readyState == 4 && xhr.status == 200) {
            document.getElementById('q'+num).className = "question submitted";
            console.log("response: " + xhr.responseText);
        } else {
            console.log(xhr.readyState, xhr.status, xhr.response);
        }
    }
    /*
    xhr.onload = function() {
        if (xhr.responseText.length == 0) {
            document.getElementById("notice").innerHTML = "this page auto-updates and is the most recent available";
        } else {
            var score = xhr.responseText.split("\n")[0];
            var txt = xhr.responseText.substr(score.length+1);
            document.getElementById("score").innerHTML = score;
            if (txt.length == 0) {
                document.getElementById("notice").innerHTML = "this page auto-updates and is the most recent available";
            } else if (txt.substr(0,3) == '201') {
                document.getElementById("notice").innerHTML = "New question available. Reloading...";
                location.assign("#");
            } else {
                document.getElementById("notice").innerHTML = "auto-check for new data broken";
            }
        }
    }
    */
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
function onload() {
    var remaining = Number(document.getElementById('clock').innerHTML);
    due += remaining * 1000;
    timer = setInterval(tick, 1000);
}

    </script>
</head>
<body onload="onload()">
    <center><a href="<?= $homepage ?>">Return to course page</a></center><?php

require_once "qshowlib.php";

showQuiz($_GET['qid']);

?>
<a href="<?php
if ($_GET['asuser']) echo '.?asuser='.$user;
else echo '.';
?>" style="text-align:center; display:block;">Return to main page</a>
<center><a href="../">Return to course page</a></center>
</body>
</html>
