<?php
$host = "localhost";
$user = "root";
$password = "";
$database = "cricket_ticket";

$link = mysqli_connect($host, $user, $password, $database);

if(!$link){
    die("Connection failed:" . mysqli_connect_error());
}
