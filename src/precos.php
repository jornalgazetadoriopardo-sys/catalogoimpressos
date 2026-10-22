<?php
declare(strict_types=1);

/** Margem em %: a do produto vale sobre a da categoria, que vale sobre a padrão. */
function margem_efetiva($margemProduto, $margemCategoria): float
{
    foreach ([$margemProduto, $margemCategoria] as $m) {
        if ($m !== null && $m !== '') {
            return (float) $m;
        }
    }
    return (float) cfg('margem_padrao');
}

function preco_venda(float $custo, float $margem): float
{
    $valor = $custo * (1 + $margem / 100);
    switch (cfg('arredondamento')) {
        case 'inteiro':
            return ceil(round($valor, 2));
        case '90':
            $v = floor($valor) + 0.90;
            return round($v + 0.0001 < $valor ? $v + 1 : $v, 2);
        default:
            return round($valor, 2);
    }
}

/** Prazo total exibido ao cliente: produção + prazo extra configurado. */
function prazo_producao(int $diasOrigem): int
{
    return $diasOrigem + (int) cfg('prazo_extra_dias');
}

function cep_limpo(string $cep): string
{
    return substr(preg_replace('/\D/', '', $cep), 0, 8);
}

/**
 * Frete para um CEP: procura a faixa mais específica cadastrada; se nenhuma
 * cobrir o CEP usa o frete padrão. Frete grátis acima do valor configurado.
 */
function calcular_frete(string $cep, float $subtotal = 0): ?array
{
    $cep = cep_limpo($cep);
    if (strlen($cep) !== 8) {
        return null;
    }
    $regiao = db_um(
        'SELECT * FROM frete_regioes WHERE cep_inicio <= ? AND cep_fim >= ?
         ORDER BY (CAST(cep_fim AS UNSIGNED) - CAST(cep_inicio AS UNSIGNED)) ASC LIMIT 1',
        [$cep, $cep]
    );
    $frete = $regiao
        ? ['nome' => $regiao['nome'], 'valor' => (float) $regiao['valor'], 'prazo' => (int) $regiao['prazo_dias']]
        : ['nome' => 'Entrega', 'valor' => (float) cfg('frete_padrao_valor'), 'prazo' => (int) cfg('frete_padrao_prazo')];

    $gratisAcima = (float) cfg('frete_gratis_acima');
    if ($gratisAcima > 0 && $subtotal >= $gratisAcima) {
        $frete['valor'] = 0.0;
        $frete['gratis'] = true;
    }
    return $frete;
}
