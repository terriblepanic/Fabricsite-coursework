<?php
session_start();
session_destroy();
header("Location: /fabricsite/index.php");
exit;
