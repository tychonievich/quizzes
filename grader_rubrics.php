<?php

/**
 * Given a quiz and one of its questions, return an array.
 * keys are users
 * values are arrays with five keys:
 *  "submitted":true/false if image, or their answer array
 *  "comments":their comments, or null
 *  "graded":[1,1,0.5] or null
 *  "feedback":grader comments, or null
 *  "id":quasi-anonymous number
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
echo "<script>console.log('pending',".json_encode($users).");</script>";
    if (isset($rev["$slug-graded"]))
        $users = array_merge($users, $rev["$slug-graded"]);
    
    $qaid = makeQuasiAnonID($quizid);

    $ans = array();
    foreach($users as $user) {
        $sobj = aparse($qobj, $user);
        $ans[$user] = array(
            "submitted" => $q['type'] == 'image' 
                ? file_exists("log/$quizid/$user-$slug") 
                : null,
            "comments" => isset($sobj[$slug]['comments']) ? $sobj[$slug]['comments'] : null,
            "graded" => isset($sobj[$slug]['rubric']) ? $sobj[$slug]['rubric'] : null,
            "feedback" => isset($sobj[$slug]['feedback']) ? $sobj[$slug]['feedback'] : null,
            "id" => isset($qaid[$user]) ? $qaid[$user] : 0,
        );
    }

    return $ans;
}

/**
 * Given a quiz, one of its questions, and a grader ID, return an array.
 * keys are students ids.
 * values are arrays with five keys:
 *  "submitted":true/false if image, or their answer array
 *  "comments":their comments, or null
 *  "graded":[1,1,0.5] or null
 *  "feedback":grader comments, or null
 *  "id":quasi-anonymous number
 * keys are ordered with last-first-graded first
 */
function get_graded_rubrics($quizid, $q, $grader) {
    $slug = $q['slug'];
    $qobj = qparse($quizid);
    $rev = get_review($quizid);

    $qaid = makeQuasiAnonID($quizid);

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
                "feedback" => isset($sobj[$slug]['feedback']) ? $sobj[$slug]['feedback'] : null,
                "id" => isset($qaid[$bits[0]]) ? $qaid[$bits[0]] : 0,
            );
        }
    }

    return $ans;
}

/**
 * PHP does not have stateful random number generators,
 * which makes shuffling IDs for each quiz differently tricky.
 * So we hack it using our own LCG
 */
function seedShuffle($array, $seed) {
    $num = intval(md5($seed), 16);
    $n = count($array);
    foreach($array as $i=>$val) {
        $num *= 1103515245;
        $num += 12345;
        $num &= 0x7fffffff;
        $j = $j + (($num&0x3fffffff)%$n);
        $n -= 1;
        if ($i != $j) {
            $array[$i] = $array[$j];
            $array[$j] = $val;
        }
    }
}
/**
 * Short "Anonymous" IDs of students
 * Note that this will change if a new user views the quiz...
 */
function makeQuasiAnonID($quizid) {
    $ans = array();
    $whom = glob("log/$quizid/*.log");
    $idx = array_keys($whom);
    seedShuffle($idx, $quizid);
    foreach($whom as $i=>$path) {
        $id = basename($path,".log");
        $ans[$id] = 1000+$idx[$i];
    }
    return $ans;
}


function show_rubric($quizid, $q, $mq) {
    global $user;
    $slug = $q['slug'];
    
    echo "<p>";
    if (isset($_GET['mine'])) {
        $qs = preg_replace('/[&]mine\b(=[^&]*)?/', '', $_SERVER['QUERY_STRING']);
        echo "Viewing submissions graded by $user; <a href='?$qs'>return to main grading page</a>. ";
    } else {
        echo "Viewing all submissions; <a href='?$_SERVER[QUERY_STRING]&mine'>view just those you've graded</a>. ";
    }
    echo "<input type='button' value='Show all images' onclick='revealAll()'></input>";
    echo "</p>";


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
        if (!$ans) echo '<em>(student left answer blank)</em>';
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
        echo "</div><div class='rubric'><span class='qaid'>Submssion ID: $details[id]</span><form onsubmit='sendGrade(event, \"$student\")' name='$student'>";
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
        echo "<input type='button' onclick='skip(\"$student\")' value='skip this quiz'/>";
        
        echo"</div></div>";
    }
    
    ?>
    <script id="separator" type="text/javascript">
    function reveal(id) {
        let a = document.querySelector('#'+id+' a[onclick]');
        if (!a) return;
        a.removeAttribute('onclick');
        let img = document.createElement('img');
        img.src = 'imgshow.php?asuser='+id+'&qid=<?=$quizid?>&slug=<?=$slug?>';
        img.classList.add('pageview');
        img.setAttribute('loading','lazy');
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

    async function revealAll() {
        document.querySelectorAll('div.grade1[id]').forEach(x => reveal(x.id));
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

    function skip(id) {
        document.body.insertBefore(
            document.getElementById(id),
            document.getElementById('separator')
        )
        viewing()
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



?>
