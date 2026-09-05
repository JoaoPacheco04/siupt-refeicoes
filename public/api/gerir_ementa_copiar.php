<?php
/**
 * Endpoint AJAX — Copiar os pratos de uma semana para outra.
 * POST: inicio_origem, fim_origem, inicio_destino, fim_destino (Y-m-d)
 *
 * Copia nome + tipo de cada prato (Seg→Seg, Ter→Ter, …),
 * sem publicar e sem duplicar se já existir o mesmo tipo nesse dia.
 */

require_once __DIR__ . "/../../src/Support/Auth.php";
require_once __DIR__ . "/../../src/Infrastructure/Database.php";

header("Content-Type: application/json; charset=utf-8");

exigirPost();
$utilizador = exigirLogin("admin_cantina", true);
verificarCsrfToken(true);

$inicioOrigem  = trim($_POST["inicio_origem"]  ?? "");
$fimOrigem     = trim($_POST["fim_origem"]     ?? "");
$inicioDestino = trim($_POST["inicio_destino"] ?? "");
$fimDestino    = trim($_POST["fim_destino"]    ?? "");

foreach ([$inicioOrigem, $fimOrigem, $inicioDestino, $fimDestino] as $d) {
    $dt = DateTime::createFromFormat("Y-m-d", $d);
    if (!$dt || $dt->format("Y-m-d") !== $d) {
        echo json_encode(["status" => "erro", "mensagem" => "Datas invalidas."]);
        exit;
    }
}

if ($inicioOrigem === $inicioDestino) {
    echo json_encode(["status" => "erro", "mensagem" => "Origem e destino sao a mesma semana."]);
    exit;
}

try {
    $pdo = Database::conexao();

    $stmt = $pdo->prepare("
        SELECT RM_NOME, RM_TP_ID, RM_DATA
        FROM restaurante_menu
        WHERE RM_DATA BETWEEN ? AND ? AND RM_DATA IS NOT NULL
        ORDER BY RM_DATA, RM_TP_ID
    ");
    $stmt->execute([$inicioOrigem, $fimOrigem]);
    $pratosOrigem = $stmt->fetchAll();

    if (empty($pratosOrigem)) {
        echo json_encode(["status" => "erro", "mensagem" => "A semana anterior não tem pratos configurados para copiar."]);
        exit;
    }

    $dtInicioOrigem  = new DateTime($inicioOrigem);
    $dtInicioDestino = new DateTime($inicioDestino);
    $diffDias = (int) round(($dtInicioDestino->getTimestamp() - $dtInicioOrigem->getTimestamp()) / 86400);
    $offsetMod = ($diffDias >= 0 ? "+{$diffDias}" : "{$diffDias}") . " days";

    $stmtExist = $pdo->prepare("
        SELECT CONVERT(VARCHAR(10), RM_DATA, 120) AS data, RM_TP_ID
        FROM restaurante_menu
        WHERE RM_DATA BETWEEN ? AND ? AND RM_DATA IS NOT NULL
    ");
    $stmtExist->execute([$inicioDestino, $fimDestino]);
    $jaExistem = [];
    foreach ($stmtExist->fetchAll() as $row) {
        $jaExistem[$row["data"] . "|" . $row["RM_TP_ID"]] = true;
    }

    $stmtInsert = $pdo->prepare("
        INSERT INTO restaurante_menu (RM_NOME, RM_TP_ID, RM_DATA, RM_PUBLICADO)
        VALUES (?, ?, ?, 0)
    ");

    $copiados  = 0;
    $ignorados = 0;

    foreach ($pratosOrigem as $p) {
        $dataOrigem = $p["RM_DATA"] instanceof DateTime
            ? $p["RM_DATA"]->format("Y-m-d")
            : substr((string) $p["RM_DATA"], 0, 10);

        $dtTemp = new DateTime($dataOrigem);
        $dtTemp->modify($offsetMod);
        $dataDestino = $dtTemp->format("Y-m-d");

        // Não copia para datas anteriores a hoje nem para feriados
        if ($dataDestino < date('Y-m-d') || Database::ehFeriado($dataDestino)) {
            $ignorados++;
            continue;
        }

        $chave = $dataDestino . "|" . $p["RM_TP_ID"];

        if (isset($jaExistem[$chave])) {
            $ignorados++;
            continue;
        }

        $stmtInsert->execute([$p["RM_NOME"], $p["RM_TP_ID"], $dataDestino]);
        $jaExistem[$chave] = true;
        $copiados++;
    }

    echo json_encode(["status" => "ok", "copiados" => $copiados, "ignorados" => $ignorados]);
} catch (Exception $e) {
    error_log("Erro em gerir_ementa_copiar: " . $e->getMessage());
    echo json_encode(["status" => "erro", "mensagem" => "Erro ao copiar semana: " . $e->getMessage()]);
}
