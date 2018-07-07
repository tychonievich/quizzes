<?php 

require_once "qparse.php";
require_once "JSON.php";
JSON::$use = SERVICES_JSON_LOOSE_TYPE;

function getAnswers($logfile) {
    $ans = array();
    $fh = fopen($logfile, "rb");
    if (!$fh) return $ans;
    while(($jsdata = fgets($fh)) !== FALSE) {
        $obj = JSON::parse($jsdata);
        if ($obj === null) continue;
        if (array_key_exists('grade', $obj)) {
            $slug = $obj['slug'];
            if (!array_key_exists($slug, $ans)) continue;
            $ans[$slug]['grade'] = $obj['grade'];
            $ans[$slug]['feedback'] = $obj['feedback'];
        } else if (array_key_exists('slug', $obj)) {
            $show = array('answer'=>$obj['answer']);
            if (array_key_exists('comments', $obj))
                $show['comments'] = $obj['comments'];
            else
                $show['comments'] = "";
            $ans[$obj['slug']] = $show;
        }
    }
    return $ans;
}

function grade($part, $stat, $regraded=TRUE) {
    if (!$stat) $stat = array();
    
    $score = 0;
    
    if (array_key_exists('choices', $part)) {
        $yes = 0;
        $no = 0;
        foreach($part['choices'] as $key=>$val) {
            $chosen = array_key_exists('answer', $stat) && in_array($val['slug'], $stat['answer']);
            if ($val['remove']) {
            } else if ($val['free']) {
                $yes += 1;
            } else if ($val['correct']) {
                if ($chosen) $yes += 1;
                else $no += 1;
            } else {
                if ($chosen) $no += 1;
                else if ($part['checkbox']) $yes += 1;
            }
        }
        if ($part['checkbox'] && ($yes+$no) > 0) {
            $score = $yes/($yes+$no);
        } else {
            $score = $yes > 0 ? 1 : 0;
        }
    } else {
        $correctAns = false;
        if (array_key_exists('answer', $stat)) {
            if ($part['solution'][0] == '/') {
                $correctAns = preg_match($part['solution'], $stat['answer'][0]);
            } else {
                $correctAns = trim($stat['answer'][0]) == $part['solution'];
            }
        }
        $score = $correctAns ? 1 : 0;
    }
    
    if ($regraded && array_key_exists('grade', $stat) && $stat['grade'] !== '') { $score = $stat['grade']; }

    if (array_key_exists('points', $part)) $score *= $part['points'];
    
    return $score;
}



function histOf($qid) {
    if ($qid != basename($qid) || !file_exists("log/$qid")) return FALSE;
    if (file_exists("log/$qid/hist.json"))
        return JSON::parse(file_get_contents("log/$qid/hist.json"));

    # FIXME(cr4bd): Added to workaround breakage of histOf()
    return FALSE;

    $qs = array();
    $h = qparse("questions/$qid.md", $qs);
    $due = new DateTime($h['due']);
    $now = new DateTime();
    if ($now < $due) return FALSE;
    
    $hist = array();
    
    foreach($qs as $idx=>$mq) {
        foreach($mq["parts"] as $idx2=>$q) {
            $tmp = array("right"=>0,"total"=>0,"part"=>$q);
            if (array_key_exists('choices', $q)) {
                $tmp["type"] = "mc"; // multiple choice
                foreach($q["choices"] as $idx3=>$choice) {
                    $tmp[$choice["slug"]] = 0;
                }
            } else if (array_key_exists('solution', $q)) {
                if ($q['solution'][0] == '/') {
                    $tmp["type"] = "re"; // regular expression
                } else {
                    $tmp["type"] = "sa"; // short answer
                }
                $tmp["key"] = $q['solution'];
            }
            // TODO: handle fill-in-the-blank
            $hist[$q["slug"]] = $tmp;
        }
    }
    
    foreach(glob("log/$qid/*.log") as $j=>$logname) { 
        $ans = getAnswers($logname);
        foreach($ans as $q => $a) {
            if (!array_key_exists($q, $hist)) continue; // changed quiz?
            if ($hist[$q]["type"] == "mc") {
                foreach($a['answer'] as $i=>$slug) {
                    $hist[$q][$slug] += 1;
                }
            }
            $hist[$q]['right'] += grade($hist[$q]["part"], $a);
            $hist[$q]['total'] += 1;
        }
    }
    
    $score = 0;
    foreach($hist as $k=>$v) {
         unset($hist[$k]['part']);
         $score += $hist[$k]["right"] / $hist[$k]["total"];
    }
    $hist["score"] = $score;
    
    file_put_contents("log/$qid/hist.json", json_encode($hist));
    
    return $hist;
}



?>
