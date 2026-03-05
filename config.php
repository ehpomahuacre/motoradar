<?php

function conexion_postgres()
{
$host = "localhost";
$port = "5433";
$dbname = "Noticias";
$user = "postgres";
$password = "123456";
  try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
  } catch (PDOException $e) {
    echo "Error de conexión a PostgreSQL: " . $e->getMessage();
    exit;
  }
}



// Ejemplo de conexión con PDO
// $host = 'ep-summer-snow-a8bvf36o-pooler.eastus2.azure.neon.tech';
// $db = 'Noticias';
// $user = 'neondb_owner';
// $password = 'npg_cQ6ZNniDr3lu';
// $port = '5432';

// $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

// try {
//     $pdo = new PDO($dsn, $user, $password);
//     $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//     echo "Conexión exitosa a Neon.";
// } catch (PDOException $e) {
//     echo "Error de conexión: " . $e->getMessage();
// }
?> 