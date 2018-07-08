<?php

header('Content-Type: text/plain; charset=utf-8');
ob_start('ob_gzhandler');
include 'CSPagePasswords.php';
$p = new CSPagePasswords();
echo htmlentities($p->txt2php());
ob_end_flush();

?>