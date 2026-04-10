<?php

function conexion_postgres()
{
$host = "trolley.proxy.rlwy.net";
$port = "20856";
$dbname = "railway";
$user = "postgres";
$password = "AtWLHOHyCxASyPXeHhcZqLEEhOFOsnGS";
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
