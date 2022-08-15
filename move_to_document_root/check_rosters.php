<?php
if ($_SERVER['SCRIPT_NAME'] == "/check_rosters.php") {
    session_start();
    if (!isset($_SESSION['user'])) {
        $_SESSION['redirect'] = $_SERVER['SCRIPT_NAME'];
        header("Location: https://cs418.cs.illinois.edu/authenticate.php");
        exit();
    }
}

/**
 * my.cs downloads student rosters in HTML format with an XLS file ending.
 * This function coverts from that HTML to a JSON format {id:{"Net ID":id,...}}
 * and saves the result with the given filename.
 */
function covert_roster_file($fromfile, $tofile) {
    $f = fopen($fromfile, "r");
    if (!$f) { echo "<pre>unable to open('$fromfile', 'r')</pre>"; return; }
    $t = fopen($tofile, "w");
    if (!$t) { echo "<pre>unable to open('$tofile', 'w')</pre>"; return; }
    $header = array();
    while(($line = fgets($f)) !== false) {
        $line = trim($line);
        if ($line == "</tr>") break;
        if (substr_compare($line,"<th ",0,4) == 0) {
            $header[] = substr($line, 5, -5);
        }
    }
    $linepfx = '{';
    $entrpfx = '{';
    $idx = 0;
    while(($line = fgets($f)) !== false) {
        $line = trim($line);
        if ($line == "<tr>") { $entrpfx = '{'; $idx = 0; }
        if ($line == "</tr>") { fwrite($t,"}\n"); }
        if (substr_compare($line,"<td",0,3) == 0) {
            if ($idx == 0) {
                fwrite($t, $linepfx); $linepfx = ',';
                fwrite($t, '"');
                fwrite($t, substr($line, 5, -5));
                fwrite($t, '":');
            }
            fwrite($t, $entrpfx); $entrpfx = ',';
            fwrite($t, '"');
            fwrite($t, $header[$idx]);
            fwrite($t, '":"');
            fwrite($t, substr($line, 5, -5));
            fwrite($t, '"');
            $idx += 1;
        }
    }
    fwrite($t,"}\n");
    fclose($f);
    fclose($t);
}

if (is_file("roster.json")) {
    $students = json_decode(file_get_contents("roster.json"), true);
} else { $students = []; }

if (is_file("staff.json")) {
    $staff = json_decode(file_get_contents("staff.json"), true);
} else { $staff = ["luthert"=>"admin"]; }

if (isset($staff[$_SESSION['user']])) $_SESSION['role'] = 'staff';
else if (isset($students[$_SESSION['user']])) $_SESSION['role'] = 'student';
else $_SESSION['role'] = 'guest';

if ($_SERVER['SCRIPT_NAME'] == "/check_rosters.php" && $_SESSION['role'] == 'staff') {

    ?><!DOCTYPE html><head><meta charset="utf-8"><title>Roster update tool</title></head><body>
    <?php
    if (isset($_FILES['rosterhtml'])) {
        covert_roster_file($_FILES['rosterhtml']['tmp_name'], 'roster.json');
    }
    if (isset($_POST['staffroster'])) {
        $tmp = json_decode($_POST['staffroster'], true);
        if (!is_array($tmp)) echo "<pre>Staff roster was not valid JSON</pre>";
        else if (!isset($tmp[$_SESSION['user']])) echo "<pre>Staff roster must contain current user ($_SESSION[user])</pre>";
        else {
            $staff = $tmp;
            file_put_contents("staff.json",$_POST['staffroster']);
        }
    }
    ?>
    <p>Roster last updated: <?=is_file('roster.json') ? date('Y-m-d H:i:s T', filemtime('roster.json')) : "never"?></p>
    <p>Students in roster: <?=count($students)?></p>
    <form method="POST" enctype="multipart/form-data">
    <label><input type="file" name="rosterhtml"/> Roster file, as downloaded from <a href="https://my.cs.illinois.edu/">https://my.cs.illinois.edu/</a></label>
    <input type="submit" value="Upload file"/>
    </form>
    <form method="POST">
    <p>Staff roster (must be a JSON object, <code>"id": "staftype"</code>)</p>
    <textarea name="staffroster"><?php
    if (isset($_POST['staffroster'])) { echo $_POST['staffroster']; } else {
        $prefix='{';
        foreach($staff as $id=>$tmp) {
            echo "$prefix\"$id\":\"$tmp\"\n";
            $prefix = ',';
        }
        echo '}';
    }
    ?></textarea>
    <script>
        const textarea = document.querySelector("textarea");
        textarea.addEventListener("input", function (e) {
          this.style.height = "auto";
          this.style.height = this.scrollHeight + "px";
        });
        textarea.dispatchEvent(new InputEvent('input'));
    </script>
    <input type="submit" value="Revise staff list"/>
    </form>
    </body></html><?php
}

?>
