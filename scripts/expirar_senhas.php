<?php

use App\Infrastructure\Database;

require_once __DIR__ . '/../src/Infrastructure/Database.php';

$pdo = Database::conexao();

// 1. Compras pendentes de hoje nunca pagas -> expiradas
$stmt1 = $pdo->prepare("UPDATE compras SET estado = 'expirada'
                         WHERE estado = 'pendente' AND data_refeicao <= CAST(GETDATE() AS DATE)");
$stmt1->execute();
$expiradas = $stmt1->rowCount();

// 2. Compras pagas de dias passados nunca levantadas -> nao_levantada
$stmt2 = $pdo->prepare("UPDATE compras SET estado = 'nao_levantada'
                         WHERE estado = 'paga' AND data_refeicao < CAST(GETDATE() AS DATE)");
$stmt2->execute();
$naoLevantadas = $stmt2->rowCount();

// 3. Limpar tentativas de PIN com mais de 1 dia
$stmt3 = $pdo->prepare("DELETE FROM tentativas_pin WHERE data_tentativa < DATEADD(DAY, -1, GETDATE())");
$stmt3->execute();
$tentativasApagadas = $stmt3->rowCount();

$log = date('Y-m-d H:i:s') . " | expiradas: $expiradas | nao_levantadas: $naoLevantadas | tentativas_apagadas: $tentativasApagadas" . PHP_EOL;
file_put_contents(__DIR__ . '/expirar_senhas.log', $log, FILE_APPEND);

echo $log;
