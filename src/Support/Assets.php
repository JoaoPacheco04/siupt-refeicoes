<?php
/**
 * Gera o URL de um ficheiro estático com um parâmetro de versão,
 * evitando que o navegador utilize uma versão em cache.
 *
 * @param string $caminho Caminho relativo do ficheiro.
 *
 * @return string URL do ficheiro com o parâmetro de versão.
 */
function assetUrl(string $caminho): string
{
    $caminhoCompleto = __DIR__ . '/../../public/' . $caminho;
    $versao = file_exists($caminhoCompleto) ? filemtime($caminhoCompleto) : time();

    return $caminho . '?v=' . $versao;
}