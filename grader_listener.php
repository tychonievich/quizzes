<?php 
require_once "tools.php";
if (!$isstaff) exit('{"error":"must be staff to record grade adjustments"}');

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!isset($data['kind'])) exit("invalid request:\n$raw\n".json_encode($data));

if (isset($data['slug']) && isset($data['user'])) {
    putlog("$data[quiz]/regrades.log", json_encode(array(
        'student'=>$data['user'],
        'task'=>$data['slug'],
        'add'=>false,
        'date'=>date('Y-m-d H:i:s'),
    ))."\n");
}

if ($data['kind'] == 'rubric') {
    // {"quiz":quiz_id, "slug":question_id
    // ,"user":student_id,
    // ,"reply":"very nice!", "rubric":[1,1,1,0.5],
    // ,"lastOffset":123
    // }
    
    $path = "log/$data[quiz]/gradelog_$data[slug].lines";
    if (isset($data['user'])) {
        putlog("$data[quiz]/$data[user].log", json_encode(array(
            'slug'=>$data['slug'],
            'rubric'=>$data['rubric'],
            'feedback'=>$data['reply'],
            'date'=>date('Y-m-d H:i:s'),
        ))."\n");

        // store in line with tab because JSON escapes it
        $fh = fopen($path, "a+");
        fwrite($fh, implode("\t", array(
            $data['user'],
            json_encode($data['rubric']),
            json_encode($data['reply']),
            date('Y-m-d H:i:s'),
            $user,
        ))."\n");
    } else { // just ping
        $fh = fopen($path, "r");
    }
    
    // rubric grading is partially synchronized, so reply with new data since last transmission
    fseek($fh, $data['lastOffset']);
    fpassthru($fh);
    fclose($fh);
} else if ($data['kind'] == 'reply') {
    $path = "log/$data[quiz]/adjustments_$data[slug].csv";
    $fh = fopen($path, "a");
    fputcsv($fh, array(
        $data['user'], 
        isset($data['score']) ? $data['score'] : '', 
        $data['reply'], 
        $user, 
        date('Y-m-d H:i:s')
    ));
    fclose($fh);
    if (isset($data['score'])) {
        putlog("$data[quiz]/$data[user].log", json_encode(array(
            'slug'=>$data['slug'],
            'grade'=>$data['score'],
            'feedback'=>$data['reply'],
            'date'=>date('Y-m-d H:i:s'),
        ))."\n");
    } else {
        putlog("$data[quiz]/$data[user].log", json_encode(array(
            'slug'=>$data['slug'],
            'feedback'=>$data['reply'],
            'date'=>date('Y-m-d H:i:s'),
        ))."\n");
    }
} else if ($data['kind'] == 'key') {
    $fname = "log/$data[quiz]/key_$data[slug].json";
    // race condition if multiple concurrent graders; maybe add file locking?
    $ext = array($data['key']=>$data['val']);
    if (file_exists($fname)) $ext += json_decode(file_get_contents($fname),true);
    file_put_contents_recursive($fname,json_encode($ext));
} else {
    exit("unknown grader action kind \"$data[kind]\"");
}

if (file_exists("cache/$data[quiz]-hist.json"))
    unlink("cache/$data[quiz]-hist.json"); // grades changed

?>
