<?php 
require_once "staff.php";

function putlog($name, $line) {
    umask(0); // discouraged but important for mkdir
    mkdir(dirname("log/".$name), 0777, true);
    file_put_contents('log/'.$name, $line, FILE_APPEND);
    chmod('log/'.$name, 0666);
}

function firstAccess($name) {
    global $user;
    if (!file_exists("log/$name/$user.log")) return array();
    $ans = array();
    $fh = fopen("log/$name/$user.log", "rb");
    while(($jsdata = fgets($fh)) !== FALSE) {
        $obj = JSON::parse($jsdata);
        if (array_key_exists('date', $obj)) return $obj['date'];
    }
    return date('Y-m-d H:i:s');
}


require_once "JSON.php";
JSON::$use = SERVICES_JSON_LOOSE_TYPE;

$data = JSON::parse(file_get_contents("php://input"));
if ($data['user'] != $user && !$isstaff) {
    http_response_code(403);
    echo 'user '.$user.' sent as '.$data['user'];
    exit;
}
unset( $data['user'] );

$qid = $data['quiz'];
unset( $data['quiz'] );
if (strpos($qid,'/') !== FALSE || strpos($qid, "..") !== FALSE) {
    http_response_code(403);
    echo 'invalid quiz: ';
    var_dump($quiz);
    exit;
}

require_once "qparse.php";
$meta = qparse("questions/$qid.md");
$started = strtotime(firstAccess($qid));
$hours = floatval($meta['hours']);
if ($hours == 0) $hours = 8765.8128; // 1 year
if (array_key_exists($user, $extra)) $hours *= $extra[$user];
$due1 = $started + ($hours*60*60);
$due2 = strtotime($meta['due']);
$due = min($due1, $due2);
// var_dump(array($due1,$due2,$due,time()));
$remaining = $due - time();
if ($remaining < 0) {
    http_response_code(403);
    echo 'too little time remaining: ';
    var_dump($remaining);
    exit;
}

$path = "$qid/$user.log";
$data['date'] = date('Y-m-d H:i:s');
putLog($path, json_encode($data)."\n");

?>
﻿
