<?php 
require_once "tools.php";
if (!$isstaff) exit('must be staff to record grade adjustments');

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);
if (!isset($data['kind'])) exit("invalid request:\n$raw\n".json_encode($data));

if ($data['kind'] == 'reply') {
    $path = "log/$data[quiz]/adjustments_$data[slug].csv";
    $fh = fopen($path, "a");
    fputcsv($fh, array($data['user'], $data['score'], $data['reply'], $user, date('Y-m-d H:i:s')));
    fclose($fh);
    putlog("$data[quiz]/$data[user].log", json_encode(array(
        'slug'=>$data['slug'],
        'grade'=>$data['score'],
        'feedback'=>$data['reply'],
    ))."\n");
    if (file_exists("log/$data[quiz]/hist.json"))
        unlink("log/$data[quiz]/hist.json"); // grades changed
} else if ($data['kind'] == 'key') {
    $fname = "log/$data[quiz]/key_$data[slug].json";
    // race condition if multiple concurrent graders; maybe add file locking?
    $ext = array($data['key']=>$data['val']);
    if (file_exists($fname)) $ext += json_decode(file_get_contents($fname),true);
    file_put_contents_recursive($fname,json_encode($ext));
    if (file_exists("log/$data[quiz]/hist.json"))
        unlink("log/$data[quiz]/hist.json"); // grades changed
} else {
    exit("unknown grader action kind \"$data[kind]\"");
}

?>
