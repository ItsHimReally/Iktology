<?php
function connectDB() {
    $login = "";
    $pass = "";
    $server = "";
    $name_db = "";
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $link = mysqli_connect($server, $login, $pass, $name_db);
    mysqli_set_charset($link, 'utf8mb4');
    return $link;
}
?>
