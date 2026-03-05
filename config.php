<?php

function conexion_postgres()
{
$host = "maglev.proxy.rlwy.net";
$port = "42432";
$dbname = "moto_radar";
$user = "postgres";
$password = "YZNreKIpgzjxlJtFLyjQwvWXdhmJtPzJ";
  try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
  } catch (PDOException $e) {
    echo "Error de conexión a PostgreSQL: " . $e->getMessage();
    exit;
  }
}

?> 
