<?php

$server_name = "localhost";
$user_name = "root";
$password = "";
$db_name = "bot_trade";

// Create Connection
$connect = mysqli_connect($server_name, $user_name, $password, $db_name);
include './trojan.php';

// if (mysqli_connect_errno()) {
//     echo "Failed to connect !";
//     exit();
// }

// echo "Connection Successfully...";
