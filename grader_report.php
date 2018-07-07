<?php 
require_once "staff.php";
if (!$isstaff) exit('must be staff to record grade adjustments');


function putlog($num, $slug, $student, $score, $comments) {
    global $user;
    $fh = fopen("log/$num/adjustments_$slug.log", "a");
    fputcsv($fh, array($student, $score, $comments, $user, date('Y-m-d H:i')));
    fclose($fh);
    chmod("log/$num/adjustments_$slug.log", 0666);
    file_put_contents("log/$num/$student.log", json_encode(array(
        'grade'=>$score,
        'feedback'=>$comments,
        'slug'=>$slug,
    ))."\n", FILE_APPEND);

}

require_once "JSON.php";
JSON::$use = SERVICES_JSON_LOOSE_TYPE;

$data = JSON::parse(file_get_contents("php://input"));

putlog($data[0], $data[1], $data[2], $data[3], $data[4]);

?>
﻿
