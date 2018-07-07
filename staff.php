<?php
$user = $_SERVER['PHP_AUTH_USER']; 

$homepage = "https://www.cs.virginia.edu/luther/4810/";

$staff = array("lat7h");
$extra = array(
    # student => extra time portion
);

$quizignore_by_student = array(
    # student => number to drop
);

$quizadjust_by_student_quiz = array(
);

$quizadjust_silent_by_student_quiz = array(
);

$isstaff = in_array($user, $staff);
if ($isstaff && array_key_exists('asuser', $_GET)) { $user = $_GET['asuser']; }
?>
