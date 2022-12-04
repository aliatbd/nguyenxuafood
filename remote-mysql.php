<?php
$host= gethostname(); //Lấy Tên hostname tại server chứa website.
$ip = gethostbyname($host); //Lấy IP của Server

$servername = "103.77.162.9"; //Thay "localhost" bằng IP máy chủ Hosting
$username = "aliat920_nguyenxua"; //Thay "username" bằng tên database
$password = "19slS@?R23O7"; //Thay "password" bằng mật khẩu database

// Create connection
$conn = mysqli_connect($servername, $username, $password);

// Check connection
if (!$conn) 
{die("Kết nối thất bại: " . mysqli_connect_error());
}
echo "Kết nối từ xa thành công tới máy chủ MySQL từ xa<br>";
echo "IP VPS: $ip<br>";
echo "IP máy chủ Hosting: $servername";
?>
