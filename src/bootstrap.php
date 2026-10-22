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
        'intervalo_sync_horas'=> '24',
        'atraso_requisicao_ms'=> '300',
        'requisicoes_paralelas'=> '4',
        'modelo_produtos'     => '40',   // 0 = importar todos os produtos
        'modelo_combinacoes'  => '12',   // 0 = todas as combinações de cada produto
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

function link_whatsapp(string $mensagem): string
{
    $numero = preg_replace('/\D/', '', cfg('whatsapp'));
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensagem);
}
