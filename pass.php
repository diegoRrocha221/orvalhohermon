<?php
$senha = "diego123";
$hash = password_hash($senha, PASSWORD_BCRYPT, ["cost" => 10]);
echo $hash;