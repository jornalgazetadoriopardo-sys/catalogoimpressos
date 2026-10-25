<?php
declare(strict_types=1);

function orcamento_numero(int $id): string
{
    return str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

/** Carrega o orçamento com itens e totais calculados. */
function carregar_orcamento(string $campo, $valor): ?array
{
    if (!in_array($campo, ['id', 'token'], true)) {
        return null;
    }
    $o = db_um("SELECT * FROM orcamentos WHERE $campo = ?", [$valor]);
    if (!$o) {
        return null;
    }
    $o['itens'] = db_todos('SELECT * FROM orcamento_itens WHERE orcamento_id = ? ORDER BY ordem, id', [$o['id']]);
    $o['subtotal'] = array_sum(array_map(fn($i) => (float) $i['valor'], $o['itens']));
    $o['total'] = max(0, $o['subtotal'] + (float) $o['frete_valor'] - (float) $o['desconto']);
    $o['prazo_producao'] = $o['itens'] ? max(array_map(fn($i) => (int) $i['prazo_dias'], $o['itens'])) : 0;
    $o['numero'] = orcamento_numero((int) $o['id']);
    $o['validade'] = date('d/m/Y', strtotime($o['criado_em'] . ' +' . (int) $o['validade_dias'] . ' days'));
    return $o;
}

function url_publica_orcamento(array $o): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $https . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url_base() . 'orcamento.php?t=' . $o['token'];
}

function mensagem_orcamento(array $o): string
{
    return 'Olá, ' . $o['cliente_nome'] . "! Segue o orçamento nº {$o['numero']} da " . cfg('nome_empresa')
        . ' no valor de ' . moeda($o['total']) . ":\n" . url_publica_orcamento($o)
        . "\n\nVálido até {$o['validade']}. Qualquer dúvida, estou à disposição.";
}
