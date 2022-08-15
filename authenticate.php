<?php
session_start();
if (!$_SESSION['user']) {
    $_SESSION['redirect'] = $_SERVER['SCRIPT_NAME'];
    header("Location: https://cs418.cs.illinois.edu/authenticate.php");
    exit();
}

$user = $_SESSION['user'];
$fullname = isset($_SESSION['details']['name']) ? $_SESSION['details']['name'] : $user;
$name = isset($_SESSION['details']['given_name']) ? $_SESSION['details']['given_name'] : $fullname;

$isstaff = $_SESSION['role'] == 'staff';
$realisstaff = $isstaff;
if ($isstaff && isset($_GET['asuser'])) {
    $user = $_GET['asuser'];
    $isstaff = isset($staff[$user]);
    if (isset($students[$user]['Name'])) {
        $fullname = $students[$user]['Name'];
        $name = trim(substr($fullname, strrpos($fullname,",")+1));
    } else {
        $fullname = $user;
        $name = $user;
    }
}

$metadata = is_file('course.json') ? json_decode(file_get_contents('course.json'), TRUE) : [];

?>
