<?php
$password = 'AdminAdmin';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Hash for password " . $password . " is " . $hash;
?> 