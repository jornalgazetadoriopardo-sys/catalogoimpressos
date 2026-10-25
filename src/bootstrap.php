<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Sao_Paulo');

define('RAIZ', dirname(__DIR__));

if (!is_file(RAIZ . '/config.php')) {
    http_response_code(500);
    exit('Arquivo config.php não encontrado. Copie config.sample.php para config.php e preencha os dados.');
}

$GLOBALS['config'] = require RAIZ . '/config.php';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/precos.php';

function config(string $chave, $padrao = null)
{
    return $GLOBALS['config'][$chave] ?? $padrao;
}

function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function agora(): string
{
    return date('Y-m-d H:i:s');
}

function moeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function numero_br(string $valor): float
{
    return (float) str_replace(['.', ','], ['', '.'], trim($valor));
}

/** Monta a URL base do site (funciona em subpasta). */
function url_base(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $raizWeb = preg_replace('#/(admin|api|cron)/[^/]*$#', '/', $script);
    $raizWeb = preg_replace('#/[^/]*\.php$#', '/', $raizWeb);
    return rtrim($raizWeb, '/') . '/';
}

/** Valores padrão das configurações editáveis no admin. */
function configuracoes_padrao(): array
{
    return [
        'nome_empresa'        => 'Minha Gráfica',
        'slogan'              => 'Catálogo de impressos',
        'whatsapp'            => '',
        'email'               => '',
        'telefone'            => '',
        'endereco'            => '',
        'logo_url'            => '',
        'cor_primaria'        => '#0b5cab',
        'margem_padrao'       => '30',
        'arredondamento'      => '90',   // centavos | 90 | inteiro
        'prazo_extra_dias'    => '1',
        'frete_padrao_valor'  => '25.00',
        'frete_padrao_prazo'  => '5',
        'frete_gratis_acima'  => '0',
        'frete_texto'         => 'Frete calculado pelo CEP de entrega.',
        'retirada_ativa'      => '1',
        'retirada_texto'      => 'Retirada grátis no nosso endereço.',
        'texto_rodape'        => 'Preços sujeitos a alteração sem aviso prévio. Arte não inclusa.',
        'mostrar_unitario'    => '1',
        'atualizacao_auto_horas' => '0',  // 0 = só pelo botão "Atualizar valores"
        'atraso_requisicao_ms'=> '300',
        'requisicoes_paralelas'=> '4',
        'orc_validade_dias'   => '7',
        'orc_pagamento'       => 'PIX ou cartão. 50% na aprovação e 50% na entrega.',
        'orc_prazo_entrega'   => 'Produção após aprovação da arte e do pagamento.',
        'orc_observacoes'     => 'Arte não inclusa. Valores válidos para os arquivos enviados prontos para impressão.',
    ];
}

function cfg(string $chave): string
{
    if (!isset($GLOBALS['cfg_cache'])) {
        $GLOBALS['cfg_cache'] = configuracoes_padrao();
        foreach (db_todos('SELECT chave, valor FROM configuracoes') as $linha) {
            $GLOBALS['cfg_cache'][$linha['chave']] = (string) $linha['valor'];
        }
    }
    return $GLOBALS['cfg_cache'][$chave] ?? '';
}

function salvar_cfg(string $chave, string $valor): void
{
    db_upsert('configuracoes', ['chave' => $chave, 'valor' => $valor], ['chave']);
    $GLOBALS['cfg_cache'][$chave] = $valor;
}

/** Endereço da logo: enviada pelo admin (caminho relativo) ou um link externo. */
function logo_src(): string
{
    $logo = cfg('logo_url');
    return $logo === '' || preg_match('#^https?://#', $logo) ? $logo : url_base() . $logo;
}

/**
 * Imagens do fornecedor passam pelo img.php: ficam guardadas no servidor,
 * reduzidas, e servidas pelo próprio domínio (necessário para gerar o PDF).
 */
function img_url(?string $url, int $largura = 400): string
{
    if (!$url) {
        return url_base() . 'assets/sem-foto.svg';
    }
    return url_base() . 'img.php?w=' . $largura . '&u=' . rawurlencode($url);
}

function link_whatsapp(string $mensagem, ?string $numero = null): string
{
    $numero = preg_replace('/\D/', '', $numero ?? cfg('whatsapp'));
    if ($numero !== '' && strlen($numero) <= 11) {
        $numero = '55' . $numero; // número brasileiro digitado sem DDI
    }
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensagem);
}
