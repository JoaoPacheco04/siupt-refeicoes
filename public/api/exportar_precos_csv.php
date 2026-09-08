<?php
/**
 * Endpoint: Exportar Tabela Completa de Preços em CSV
 *
 * Exporta todos os preços vigentes de pratos principais, menus, complementos
 * e pratos extra no formato CSV compatível com o Excel (delimitador ponto e vírgula,
 * codificação UTF-8 com BOM).
 *
 * Acesso reservado ao papel admin_cantina.
 *
 * @package siupt_refeicoes
 */

require_once __DIR__ . '/../../src/Support/Auth.php';
require_once __DIR__ . '/../../src/Infrastructure/Database.php';

$utilizador = exigirLogin('admin_cantina');

$nomeFicheiro = 'tabela_precos_cantina_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header("Content-Disposition: attachment; filename=\"{$nomeFicheiro}\"");
header('Pragma: no-cache');
header('Expires: 0');

// Saída UTF-8 com BOM para garantir abertura correta no Excel em Windows
$saida = fopen('php://output', 'w');
fprintf($saida, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Cabeçalho das colunas
fputcsv($saida, [
    'Categoria',
    'Item / Refeição',
    'Descrição',
    'Preço (€)',
    'Em Vigor Desde'
], ';');

// 1. Obter preços dos tipos base
$precosBase = Database::listarPrecosVigentesTodosTipos();
$precosPorNome = [];
foreach ($precosBase as $p) {
    $precosPorNome[$p['RTP_NOME']] = $p;
}

// Categorias e metadados
$estrutura = [
    'Pratos Principais da Ementa' => [
        'Carne' => 'Prato principal à base de carne da ementa diária',
        'Peixe' => 'Prato principal de pescado da ementa diária',
        'Vegetariano' => 'Prato principal vegetariano da ementa diária',
    ],
    'Menu Completo de Refeição' => [
        'Menu Completo' => 'Fórmula com prato principal + sopa + sobremesa + bebida',
    ],
    'Itens Complementares & Bebidas' => [
        'Sopa' => 'Sopa do dia (incluída no Menu Completo ou comprada à parte)',
        'Sobremesa' => 'Sobremesa / Fruta da época (incluída no Menu Completo ou à parte)',
        'Bebida' => 'Água ou sumo (incluída no Menu Completo ou à parte)',
    ],
    'Pratos Extra' => [
        'Prato extra' => 'Preço padrão de referência para opções extra da cantina',
    ],
];

foreach ($estrutura as $catNome => $itens) {
    foreach ($itens as $tipoNome => $desc) {
        $dados = $precosPorNome[$tipoNome] ?? null;
        $precoStr = '';
        $dataStr = 'Preço de catálogo';

        if ($dados && $dados['preco_atual'] !== null) {
            $precoStr = number_format((float) $dados['preco_atual'], 2, ',', '');
        }

        if ($dados && !empty($dados['data_inicio_preco'])) {
            $dataStr = date('d/m/Y', strtotime($dados['data_inicio_preco']));
        }

        fputcsv($saida, [
            $catNome,
            $tipoNome,
            $desc,
            $precoStr,
            $dataStr,
        ], ';');
    }
}

// 2. Exportar extras individuais ativos (se existirem)
try {
    $extrasIndividuais = Database::listarDetalhesExtrasParaGestao();
    foreach ($extrasIndividuais as $extra) {
        if (empty($extra['RM_ATIVO'])) {
            continue; // apenas ativos
        }

        $precoExtra = $extra['preco_atual'] !== null
            ? number_format((float) $extra['preco_atual'], 2, ',', '')
            : '';

        fputcsv($saida, [
            'Pratos Extra (Individuais)',
            $extra['RM_NOME'],
            'Prato extra individual disponível na cantina',
            $precoExtra,
            date('d/m/Y'),
        ], ';');
    }
} catch (Exception $e) {
    // Caso ocorra algum erro nos extras específicos, o CSV continua válido com os tipos base
}

fclose($saida);
exit;
