<?php
session_start();

// session variables အကုန်ဖျက်
session_unset();

// session destroy
session_destroy();

// login page ကိုပြန်ပို့
header("Location: login.php");
exit();
?>