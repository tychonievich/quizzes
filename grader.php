<?php

require_once "tools.php";
 if (!$isstaff) {
    http_response_code(403);
    die('staff use only');
}

$_review = array();
function get_review($quizid) {
    if (isset($_review[$quizid])) return $_review[$quizid];
    if (file_exists("cache/$quizid-review.json")) {
        $ans = json_decode(file_get_contents("cache/$quizid-review.json"), true);
    } else {
        $ans = array();
        histogram($quizid, $ans);
    }
    $_review[$quizid] = $ans;
    return $ans;
}

/**
 * Given a quiz and one of its questions, return an array.
 * keys are user-submitted text
 * values are arrays with three keys:
 *  "matches":{"quiz key":weight, "quiz key":weight, ...}
 *  "users":["mst3k", ...]
 *  "decided": (null or number)
 */
function get_blanks($quizid, $q) {
    $slug = $q['slug'];
    $rev = get_review($quizid);
    if (!isset($rev["$slug-answers"])) return array();
    $ext = array();
    if (file_exists("log/$q[quizid]/key_$slug.json"))
        $ext = json_decode(file_get_contents("log/$q[quizid]/key_$slug.json"), true);

    $ans = array();
    foreach($rev["$slug-answers"] as $txt=>$users) {
        $match = array();
        foreach($q['key'] as $key) {
            $k = $key['text'];
            if(($k[0] == '/') ? preg_match($k, $txt) : $k == $txt)
                $match[$k] = $key['points'];
        }
        $ans[$txt] = array(
            'users' => $users,
            'matches' => $match,
            'decided' => (isset($ext[$txt]) ? $ext[$txt] : null),
        );
    }
    return $ans;
}

/**
 * Given a quiz and one of its questions, return an array.
 * keys are users
 * values are either null (if not reviewed) or an array with keys
 *  "feedback":"TA entered text" or "" (if no feedback text)
 *  "grade":number (new grade) or null (no change)
 */
function get_comments($quizid, $slug) {
    
    $rev = get_review($quizid);
    if (!isset($rev[$slug])) return array();
    $whom = $rev[$slug];
    $ans = array();
    foreach($whom as $k) $ans[$k] = null;
    if (file_exists("log/$quizid/adjustments_$slug.csv")) {
        $fh = fopen("log/$quizid/adjustments_$slug.csv", "r");
        while (($row = fgetcsv($fh)) !== FALSE) {
            $ans[$row[0]] = array(
                "grade" => is_numeric($row[1]) ? floatval($row[1]) : null,
                "feedback" => $row[2],
            );
        }
    }
    return $ans;
}

/**
 * Given a quiz and one of its questions, return an array.
 * keys are users
 * values are arrays with two keys:
 *  "submitted":true/false if image, or their answer array
 *  "comments":their comments, or null
 *  "graded":[1,1,0.5] or null
 *  "feedback":grader comments, or null
 * 
 * keys are ordered with ungraded first, shuffled,
 * then graded after
 */
function get_rubrics($quizid, $q) {
    $slug = $q['slug'];
    $qobj = qparse($quizid);
    $rev = get_review($quizid);

    $users = isset($rev["$slug-pending"]) ? $rev["$slug-pending"] : array();
    shuffle($users);
    if (isset($rev["$slug-graded"]))
        $users = array_merge($users, $rev["$slug-graded"]);
    
    $ans = array();
    foreach($users as $user) {
        $sobj = aparse($qobj, $user);
        $ans[$user] = array(
            "submitted" => $q['type'] == 'image' 
                ? file_exists("log/$quizid/$user-$slug") 
                : null,
            "comments" => isset($sobj[$slug]['comments']) ? $sobj[$slug]['comments'] : null,
            "graded" => isset($sobj[$slug]['rubric']) ? $sobj[$slug]['rubric'] : null,
            "feedback" => isset($sobj[$slug]['feedback']) ? $sobj[$slug]['feedback'] : null
        );
    }

    return $ans;
}

/**
 * Given a quiz, one of its questions, and a grader ID, return an array.
 * keys are students ids.
 * values are arrays with two keys:
 *  "submitted":true/false if image, or their answer array
 *  "comments":their comments, or null
 *  "graded":[1,1,0.5] or null
 *  "feedback":grader comments, or null
 * 
 * keys are ordered with last-first-graded first
 */
function get_graded_rubrics($quizid, $q, $grader) {
    $slug = $q['slug'];
    $qobj = qparse($quizid);
    $rev = get_review($quizid);
    
    $ans = array();
    $fh = fopen("log/$quizid/gradelog_$slug.lines", "r");
    while (($line = fgets($fh))) {
        $line = trim($line);
        if (!$line) continue;
        $bits = explode("\t",$line);
        if ($bits[4] == $grader) {
            $sobj = aparse($qobj, $bits[0]);
            $ans[$bits[0]] = array(
                "submitted" => $q['type'] == 'image' 
                    ? file_exists("log/$quizid/$bits[0]-$slug") 
                    : null,
                "comments" => isset($sobj[$slug]['comments']) ? $sobj[$slug]['comments'] : null,
                "graded" => isset($sobj[$slug]['rubric']) ? $sobj[$slug]['rubric'] : null,
                "feedback" => isset($sobj[$slug]['feedback']) ? $sobj[$slug]['feedback'] : null
            );
        }
    }

    return $ans;
}



function show_blanks($quizid, $q, $mq) {
    $slug = $q['slug'];
    if ($mq['text']) echo "<div class='multiquestion'>$mq[text]";
    showQuestion($q, $quizid, '', 'none', false, $mq['text'], array(''), true, true, false, true);
    if ($mq['text']) echo '</div>';
    $anum = 0;
    foreach(get_blanks($quizid, $q) as $opt => $details) {
        $anum += 1;
        echo "<div class='multiquestion";
        if (isset($details['decided'])) echo " submitted";
        echo "' id='q-$anum'>Reply: <code style='font-size:150%; border: thin solid gray'>";
        echo htmlentities($opt)."</code> – ".count($details['users'])." replies";
        $score = 0;
        foreach($details['matches'] as $key=>$weight) {
            echo "<br/>matches key($weight): <code style='font-size:150%; border: thin solid gray'>".htmlentities($key)."</code>";
            if ($weight > $score) $score = $weight;
        }
        if (isset($details['decided'])) $score = $details['decided'];
        echo "<p>Points: <input type='text' id='a-$anum' value='$score' onchange='setKey(\"$anum\",".json_encode($opt).")' onkeydown='pending($\"$anum\")'/>";
        if (!isset($details['decided']))
            echo "<input type='button' onclick='setKey(\"$anum\",".json_encode($opt).")' id='delme-$anum' value='no reply needed'/>";
        echo "</p>";
        echo "</div>";
    }
}


function show_comments($quizid, $q, $mq) {
    $qobj = qparse($quizid);
    $hist = histogram($qobj);
    
    foreach(get_comments($quizid, $q['slug']) as $user=>$details) {
        $sobj = aparse($qobj, $user);
        grade($qobj, $sobj); // annotate with score
        
        echo "<div class='multiquestion";
        if (isset($details)) echo " submitted";
        echo "' id='q-$user'>$mq[text]";
        showQuestion($q, $quizid, $user, $user, $qobj['comments']
            ,$mq['text']
            ,isset($sobj[$q['slug']]) ? $sobj[$q['slug']]
                : array('answer'=>array(),'comments'=>'')
            ,true
            ,$hist
            ,true
            ,false
            );
        $score = isset($sobj[$q['slug']]['score']) ? $sobj[$q['slug']]['score'] : 0;
        if ($q['points']) $score /= $q['points'];
        $rawscore = $score;
        $feedback = '';
        if (isset($details['grade'])) $score = $details['grade'];
        if (isset($details['feedback'])) $feedback = $details['feedback'];
        
        echo "<p>Points: <input type='text' id='a-$user' value='$score' onchange='setComment(\"$user\")' rawscore='$rawscore' onkeydown='pending(\"$user\")'/></p>";
        
        echo "<div class='tinput'><span>Feedback:</span><textarea id='r-$user' onchange='setComment(\"$user\")' onkeydown='pending(\"$user\")'";
        echo ">";
        echo htmlentities($feedback);
        echo "</textarea></div>";

        if (!isset($details))
            echo "<input type='button' onclick='setComment(\"$user\")' id='delme-$user' value='no reply needed'/>";

        echo '</div>';
    }
    ?><script>
        document.querySelectorAll('textarea').forEach(x => {
            x.style.height = 'auto';
            x.style.height = x.scrollHeight+'px';
        });
    </script><?php
}


function show_rubric($quizid, $q, $mq) {
    global $user;
    $slug = $q['slug'];
    echo "<details><summary tabindex='-1'>Question description (click to toggle view)</summary>";
    if ($mq['text']) echo "<div class='multiquestion'>$mq[text]";
    showQuestion($q, $quizid, '', 'none', false, $mq['text'], array(''), true, true, false, true);
    if ($mq['text']) echo '</div>';
    echo "</details>";

    $slug2html = array();
    if (isset($q['options']))
        foreach($q['options'] as $opt)
            $slug2html[$opt['slug']] = $opt['text'];

    $qset = isset($_GET['mine']) ? get_graded_rubrics($quizid, $q, $user) : get_rubrics($quizid, $q);

    foreach($qset as $student => $details) {
        echo "<div class='grade1' id='$student'><div class='submission'>";
        $ans = $details['submitted'];
//echo "<pre>".__LINE__." ".json_encode($details)."</pre>";
        if (!$ans) echo '(left blank)';
        else if ($ans === true)
            echo "<a onclick='reveal(\"$student\")'>view image</a>";
        else {
            echo '<div class="answer">';
            if (isset($slug2html[$ans])) echo $slug2html[$ans];
            else echo htmlentities($ans);
            echo '</div>';
        }
        if ($details['comments'])
            echo '<textarea disabled="disabled">'.htmlentities($details['comments']).'</textarea>';
        echo "</div><div class='rubric'><form onsubmit='sendGrade(event, \"$student\")' name='$student'>";
        foreach($q['rubric'] as $i=>$ri) {
            //if ($ri['hide']) continue; // messes up various other grader checks
            echo '<div class="row"><div class="cell">';
            echo "<label class='submitted'><input type='radio' name='i$i' value='1'/>1</label>";
            echo "<label class='submitting'><input type='radio' name='i$i' value='0.5'/>½</label>";
            echo "<label class='disconnected'><input type='radio' name='i$i' value='0'/>0</label>";
            echo "</div><div class='cell'>$ri[text]</div>";
            echo '</div>';
        }
        echo "<textarea name='reply'>".htmlentities($details['feedback'])."</textarea>";
        echo "<input type='submit' value='Submit Grade'/>";
        echo "</form>";
        echo "<div>Rotate image: <input type='button' onclick='spin(\"img-$student\",-90)' value='↶' tabindex='-1'></input> <input type='button' onclick='spin(\"img-$student\",90)' value='↷' tabindex='-1'></input></div>";
        echo "<div>View <a href='quiz.php?qid=$quizid&asuser=$student' target='_blank' tabindex='-1'>full student quiz</a> in new tab</div>";
        
        
        echo"</div></div>";
    }
    
    ?>
    <script id="separator" type="text/javascript">
    function reveal(id) {
        let a = document.querySelector('#'+id+' a[onclick]');
        a.removeAttribute('onclick');
        let img = document.createElement('img');
        img.src = 'imgshow.php?asuser='+id+'&qid=<?=$quizid?>&slug=<?=$slug?>';
        img.classList.add('pageview');
        while (a.lastChild) a.removeChild(a.lastChild);
        a.tabindex
        
        let div = document.createElement('div');
        div.id = 'img-'+id;
        div.style.display = 'inline-block';
        div.appendChild(img);
        a.appendChild(div);
        a.tabIndex = -1;
        
        //a.appendChild(img);
        setTimeout(()=>{
            a.href = 'imgshow.php?asuser='+id+'&qid=<?=$quizid?>&slug=<?=$slug?>'
            a.setAttribute('target','_blank');
        }, 100);
        return true;
    }

    async function postData(url, data) {
        const response = await fetch(url, {
            method: 'POST', // *GET, POST, PUT, DELETE, etc.
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        return response.text();
    }
    
    window._lastOffset = 0;
    window._timeoutID = 0;
    function acceptChanges(text) {
        window.clearTimeout(window._timeoutID);
        let delta = new TextEncoder().encode(text).length;
        if (delta > 0) {
            window._lastOffset += new TextEncoder().encode(text).length;
            text.trim().split('\n').forEach(entry => {
                bits = entry.split('\t')
                let bucket = document.getElementById(bits[0]);
                if (!bucket) return;
                bucket.classList.remove('submitting');
                bucket.classList.add('submitted');
                document.body.appendChild(bucket);
                let t = document.forms[bits[0]].elements
                JSON.parse(bits[1]).forEach((val,idx)=>{
                    t['i'+idx].value = String(val);
                })
                t['reply'].value = JSON.parse(bits[2]);
                console.log(bits[4],'graded',bits[0],'at',bits[3]);
            })
        }
        viewing()
        window._timeoutID = window.setTimeout(gradePing, 60*1000);
    }

    function viewing() {
        let queue = [
            document.querySelector('[class="grade1"]'),
            document.querySelector('[class="grade1"] ~ [class="grade1"]'),
        ];
        queue.forEach(e => {
            if (e && e.querySelector('.submission a[onclick]'))
                e.querySelector('.submission a[onclick]').click();
        })
        
        postData('grader_sync.php?qid=<?=$quizid?>&slug=<?=$slug?>', queue.map(x=>x?x.id:null)).then(txt => {
            if (!txt) return;
            //console.log('sync',txt);
            Object.entries(JSON.parse(txt)).forEach(([u,q])=>{
                if (u != '<?=$user?>') {
                    if (q[1] 
                    && (!queue[0] || q[1] != queue[0].id) 
                    && (!queue[1] || q[1] != queue[1].id)
                    && document.getElementById(q[1])) {
                        document.body.insertBefore(
                            document.getElementById(q[1]),
                            document.getElementById('separator')
                        )
                    }
                }
            });
            Object.entries(JSON.parse(txt)).forEach(([u,q])=>{
                if (u != '<?=$user?>') {
                    if (q[0] 
                    && (!queue[0] || q[0] != queue[0].id)
                    && document.getElementById(q[0])) {
                        document.body.insertBefore(
                            document.getElementById(q[0]),
                            document.getElementById('separator')
                        )
                    }
                }
            });
            let nq = [
                document.querySelector('[class="grade1"]'),
                document.querySelector('[class="grade1"] ~ [class="grade1"]'),
            ];
            if (nq.filter(x=>x).map(x=>x.id).join(' ') != queue.filter(x=>x).map(x=>x.id).join(' ')) {
                postData('grader_sync.php?qid=<?=$quizid?>&slug=<?=$slug?>', nq.filter(x=>x).map(x=>x?x.id:null))
                // ignore results to avoid infinite contesting at end
            }
        });
    }

    function sendGrade(event, id) {
        event.preventDefault();
        let t = document.forms[id].elements
        let ans = [];
        for(let i=0; i<(t.length-2)/3; i+=1) {
            if (t['i'+i].value.length == 0) {
                t['i'+i][0].parentElement.parentElement.parentElement.classList.add('disconnected');
                return;
            }
            t['i'+i][0].parentElement.parentElement.parentElement.classList.remove('disconnected');
            ans[i] = Number(t['i'+i].value);
        }
        let msg = {
            kind:'rubric',
            quiz:<?=json_encode($quizid)?>,
            slug:<?=json_encode($slug)?>,
            user:id,
            reply: t.reply.value,
            rubric: ans,
            lastOffset: window._lastOffset,
            // add lastOffset
        }
        
        let me = document.getElementById(id)

        me.classList.add('submitting');
        //document.body.appendChild(me);
        console.log('sending', msg);
        postData('grader_listener.php', msg)
            .then(acceptChanges)
            .catch(err => {
                me.classList.remove('submitting');
                me.classList.add('disconnected');
                console.error(err);
                alert('Failed to send grade to server! Check the console for more detailed log.');
            });

    }
    
    function gradePing() {
        postData('grader_listener.php', {
            kind:'rubric',
            quiz:<?=json_encode($quizid)?>,
            slug:<?=json_encode($slug)?>,
            lastOffset: window._lastOffset,
        }).then(acceptChanges);
    }
    

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
console.log('spinning',id,'by',dir);
        let e = document.getElementById(id);
console.log('spinning',e,'by',dir);
        let i = e.firstElementChild;
        let r = e.getAttribute('rot') || 0
        r = ((0|r)+dir)%360;
        setRot(r, e, i);
        e.setAttribute('rot', r);
    }

    gradePing();
    </script><?php
}



?><!DOCTYPE html><html>
    <head>
    <title>Grade <?=$metadata['quizname']?> <?=isset($_GET['qid']) ? $_GET['qid'] : ''?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="katex/katex.min.js"></script>
    <link rel="stylesheet" href="katex/katex.min.css">
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



<script type="text/javascript">//<!--
var quizid = <?=json_encode(isset($_GET['qid']) ? $_GET['qid'] : null)?>;
var slug = <?=json_encode(isset($_GET['slug']) ? $_GET['slug'] : null)?>;

function pending(num) {
    if (document.getElementById('q-'+num).className != "multiquestion submitting")
        document.getElementById('q-'+num).className = "multiquestion submitting";
}


function setKey(id, val) {
    document.getElementById('q-'+id).className = 'multiquestion submitting';
    let v = Number(document.getElementById('a-'+id).value);
    if (isNaN(v) || v<0 || v>1) {
        document.getElementById('q-'+id).className = 'multiquestion disconnected';
        return
    }
    let datum = {
        'kind':'key',
        'quiz':quizid,
        'slug':slug,
        'key':val,
        'val':v,
    }
    console.log(datum);
    ajaxSend(datum, id);

    let tmp = document.getElementById('delme-'+id)
    if (tmp) tmp.remove();
}

function setComment(id) {
    document.getElementById('q-'+id).className = 'multiquestion submitting';
    let v = document.getElementById('a-'+id).value;
    if (v != '') {
        v = Number(v)
        if (isNaN(v) || v<0 || v>1) {
            document.getElementById('q-'+id).className = 'multiquestion disconnected';
            return
        }
    }
    if (v == Number(document.getElementById('a-'+id).getAttribute('rawscore')))
        v = '';
    let r = document.getElementById('r-'+id).value;
    let datum = {
        'kind':'reply',
        'quiz':quizid,
        'slug':slug,
        'user':id,
        'score':v,
        'reply':r,
    }
    console.log(datum);
    ajaxSend(datum, id);

    let tmp = document.getElementById('delme-'+id)
    if (tmp) tmp.remove();
}

function ajaxSend(data, id) {
	var xhr = new XMLHttpRequest();
	if (!("withCredentials" in xhr)) {
		return null;
	}
	xhr.open("POST", "grader_listener.php", true);
	xhr.withCredentials = true;
    xhr.setRequestHeader("Content-type", 'application/json');
	xhr.onerror = function() {
		console.log("auto-check for new data broken");
	}
    xhr.onreadystatechange = function() { 
        if(xhr.readyState == 4) {
            console.log("done", xhr);
            if (xhr.status == 200) {
                document.getElementById('q-'+id).className = "multiquestion submitted";
                console.log("response: " + xhr.responseText);
            }
        }
    }
	xhr.send(JSON.stringify(data));
}
//--></script>
    </head>
<body><?php 


if (isset($_GET['qid']) && !isset(($qobj = qparse($_GET['qid']))['error'])) {
    $questions = array();
    $mqs = array();
    foreach($qobj['q'] as $mq) foreach($mq['q'] as $q) {
        $questions[$q['slug']] = $q;
        $mqs[$q['slug']] = $mq;
    }
    if (isset($_GET['slug']) && isset($questions[$_GET['slug']])) {
        if ($_GET['kind'] == 'blank') {
            show_blanks($_GET['qid'], $questions[$_GET['slug']], $mqs[$_GET['slug']]);
        } else if ($_GET['kind'] == 'comment') {
            show_comments($_GET['qid'], $questions[$_GET['slug']], $mqs[$_GET['slug']]);
        } else if ($_GET['kind'] == 'rubric') {
            show_rubric($_GET['qid'], $questions[$_GET['slug']], $mqs[$_GET['slug']]);
        } else {
            echo "To do: show \"$_GET[kind]\" view for $qobj[slug] question $_GET[slug]\n";
        }
    } else {
        $rev = get_review($qobj['slug']);
        ?><table><thead>
            <tr><th>Kind</th><th>Hash</th><th>Done</th><th>Text</th></tr>
        </thead><tbody>
        <?php
        /*
        $qnum = 0;
        foreach($questions as $num=>$q) {
            $qnum += 1;
            if (isset($rev["$q[slug]-answers"])) {
                echo "<tr><td>Question $qnum blank</td></tr>";
            }
            if (isset($rev["$q[slug]"])) {
                echo "<tr><td>Question $qnum comments</td></tr>";
            }
        }
        */

echo "<script>console.log(".json_encode(array_keys($rev)).")</script>";
        foreach($rev as $slug=>$val) if (substr($slug,8) == '-pending') {
            $slug = substr($slug,0,8);
            $done = count($rev["$slug-graded"]);
            $left = count($val);
            $of = $done + $left;
            echo "<tr><td>rubric</td><td><a href='?qid=$_GET[qid]&amp;slug=$slug&amp;kind=rubric'>$slug</a></td><td";
            if ($left == 0) echo ' class="submitted"';
            echo ">$done of $of";
            echo "</td><td>".$questions[$slug]['text']."</td></tr>\n";
        }
        foreach($rev as $slug=>$val) if (substr($slug,8) == '-answers') {
            $slug = substr($slug,0,8);
            echo "<tr><td>blank</td><td><a href='?qid=$_GET[qid]&amp;slug=$slug&amp;kind=blank'>$slug</a></td><td";
            $of = count($val);
            $sheet = get_blanks($qobj['slug'], $questions[$slug]);
            $left = 0;
            foreach($sheet as $obj)
                if (!isset($obj['decided'])) $left += 1;
            if ($left == 0) echo ' class="submitted"';
            echo ">".($of-$left)." of $of";
            echo "</td><td>".$questions[$slug]['text']."</td></tr>\n";
        }
        foreach($rev as $slug=>$val) if (strlen($slug) == 8) {
            echo "<tr><td>comment</td><td><a href='?qid=$_GET[qid]&amp;slug=$slug&amp;kind=comment'>$slug</a></td><td";
            $of = count($val);

            $sheet = get_comments($qobj['slug'], $slug);
            $left = 0;
            foreach($sheet as $uid=>$obj)
                if (!is_array($obj)) $left += 1;

            if ($left == 0) echo ' class="submitted"';
            echo ">".($of-$left)." of $of";
            echo "</td><td>".$questions[$slug]['text']."</td></tr>\n";
            //$done = isset($rev["$slug-done"]) ? count($rev["$slug-done"]) : 0;
            //if ($of <= $done) echo ' class="submitted"';
            //echo '>';
            //echo "${done} of $of";
            //echo "</td><td>".$questions[$slug]['text']."</td></tr>\n";
        }
        ?></tbody></table><?php
    }
} else {
    foreach(glob('questions/*.md') as $i=>$name) {
        $name = basename($name,".md");
        $qobj = qparse($name);
        if ($qobj['due'] >= time()) continue;
        echo "<br/><a href='grader.php?qid=$name'>$name: $qobj[title]</a>";
    }
}


?></body></html>
