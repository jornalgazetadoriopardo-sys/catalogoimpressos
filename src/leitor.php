<?php
declare(strict_types=1);

/** Leitura das páginas de produto do site de origem. */

function curl_produto(string $url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; CatalogoImpressos/1.0)',
        CURLOPT_HTTPHEADER     => ['Accept-Language: pt-BR,pt;q=0.9'],
    ]);
    return $ch;
}

function resultado_curl($ch, $corpo, string $url): string
{
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (!is_string($corpo) || $corpo === '' || $status >= 400) {
        throw new RuntimeException("Falha ao baixar $url (HTTP $status) " . curl_error($ch));
    }
    return $corpo;
}

function baixar(string $url): string
{
    $ch = curl_produto($url);
    $corpo = curl_exec($ch);
    try {
        return resultado_curl($ch, $corpo, $url);
    } finally {
        curl_close($ch);
    }
}

/** Baixa várias URLs em paralelo. Retorna [url => html ou Throwable]. */
function baixar_varios(array $urls): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($urls as $u) {
        $handles[$u] = curl_produto($u);
        curl_multi_add_handle($mh, $handles[$u]);
    }
    do {
        $st = curl_multi_exec($mh, $ativos);
        if ($ativos) {
            curl_multi_select($mh, 1.0);
        }
    } while ($ativos && $st === CURLM_OK);

    $saida = [];
    foreach ($handles as $u => $ch) {
        try {
            $saida[$u] = resultado_curl($ch, curl_multi_getcontent($ch), $u);
        } catch (Throwable $ex) {
            $saida[$u] = $ex;
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $saida;
}

function texto_limpo(string $html): string
{
    $t = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
    // alguns textos do site vêm com entidades codificadas duas vezes (&amp;#xD;)
    $t = html_entity_decode(html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/&#(x0*[da]|0*1[03]);/i', "\n", $t); // CR/LF que o HTML5 não decodifica
    $t = preg_replace("/[ \t\r]+/u", ' ', $t);
    return trim(preg_replace("/\n\s*/u", "\n", $t));
}

/** Lista as URLs de produto do sitemap. */
function urls_do_sitemap(string $origem): array
{
    $xml = baixar($origem . '/sitemap');
    preg_match_all('#<loc>\s*([^<]+/produto/[^<]+?)\s*</loc>#', $xml, $m);
    $urls = [];
    foreach ($m[1] as $u) {
        $u = html_entity_decode(trim($u));
        $urls[preg_replace('#\?.*$#', '', $u)] = true;
    }
    return array_keys($urls);
}

function slug_da_url(string $url): string
{
    return preg_match('~/produto/([^/?#]+)~', $url, $m) ? $m[1] : '';
}

/**
 * Extrai de uma página de produto: dados gerais, a combinação exibida
 * (especificações, prazo, adicionais), a tabela de quantidades com o SKU e o
 * preço de cada linha, e os links para as outras combinações.
 */
function ler_pagina_produto(string $html, string $url): array
{
    $slug = slug_da_url($url);
    $semScripts = preg_replace('#<script(?![^>]*ld\+json).*?</script>#s', '', $html);

    $ld = [];
    if (preg_match_all('#<script[^>]*ld\+json[^>]*>(.*?)</script>#s', $html, $m)) {
        foreach ($m[1] as $bloco) {
            $j = json_decode($bloco, true);
            if (($j['@type'] ?? '') === 'Product') {
                $ld = $j;
                break;
            }
        }
    }

    $skuAtual = (int) ($ld['sku'] ?? 0);
    if (preg_match('#name=ProdutoId id=ProdutoId value=(\d+)#', $html, $m)) {
        $skuAtual = (int) $m[1];
    }

    $nome = preg_match('#<h1 class=product-title>(.*?)</h1>#s', $html, $m)
        ? texto_limpo($m[1]) : (string) ($ld['name'] ?? $slug);

    $descricao = '';
    if (preg_match('#</h1>\s*<p class=mb-0>(.*?)<div#s', $html, $m)) {
        $descricao = texto_limpo($m[1]);
    } elseif (!empty($ld['description'])) {
        $descricao = trim(explode("\r\n", $ld['description'])[0]);
    }

    $categoria = null;
    if (preg_match_all('#<a href="?https?://[^/"\s>]+/([a-z0-9-]+)"?\s+itemprop=item>\s*<span itemprop=name>([^<]+)</span>#', $html, $m) && $m[1]) {
        $categoria = ['slug' => $m[1][0], 'nome' => texto_limpo($m[2][0])];
    }

    $imagem = '';
    if (!empty($ld['image'])) {
        $imagem = is_array($ld['image']) ? (string) $ld['image'][0] : (string) $ld['image'];
    }

    // Especificações da combinação exibida
    $especificacoes = [];
    $prazoTexto = '';
    if (preg_match('#Especifica\S+ do Produto</h2>\s*<table[^>]*>(.*?)</table>#s', $html, $m)) {
        preg_match_all('#<th scope=row>(.*?)<td>(.*?)(?=<tr|$)#s', $m[1], $linhas, PREG_SET_ORDER);
        foreach ($linhas as $l) {
            $chave = texto_limpo($l[1]);
            $valor = texto_limpo($l[2]);
            if (stripos($chave, 'prazo') === 0) {
                $prazoTexto = trim(preg_replace('/\s*Verifique.*$/su', '', $valor));
            } elseif ($chave !== '' && $valor !== '' && strcasecmp($valor, 'Nenhum') !== 0) {
                $especificacoes[$chave] = $valor;
            }
        }
    }
    $prazoDias = preg_match('#(\d+)\s*dias?\b#u', $prazoTexto, $m) ? (int) $m[1] : 0;

    // Tabela de quantidades: cada linha é um SKU; a linha marcada é o SKU atual
    $precos = [];
    if (preg_match('#<table[^>]*id=table-variacoes.*?<tbody>(.*?)</table>#s', $semScripts, $tab)) {
        $linhas = preg_split('#(?=<tr\b)#', $tab[1]);
        foreach ($linhas as $linha) {
            if (!preg_match('#name=qtd-sku[^>]*>\s*([\d.]+)\s*([^<]*)#', $linha, $q)) {
                continue;
            }
            preg_match_all('#R\$\s*([\d.]+,\d{2})(?!\s*/)#', $linha, $valores);
            if (!$valores[1]) {
                continue;
            }
            $sku = preg_match("#trocarProduto\('[^']*',\s*(\d+)\)#", $linha, $s) ? (int) $s[1] : $skuAtual;
            $precos[] = [
                'sku'        => $sku,
                'quantidade' => (int) str_replace('.', '', $q[1]),
                'unidade'    => trim(texto_limpo($q[2])) ?: 'unidades',
                'custo'      => numero_br(end($valores[1])),
            ];
        }
    }
    if (!$precos && $skuAtual && isset($ld['offers']['price'])) {
        $precos[] = ['sku' => $skuAtual, 'quantidade' => 1, 'unidade' => 'unidade', 'custo' => (float) $ld['offers']['price']];
    }

    // Acabamentos adicionais opcionais
    $adicionais = [];
    if (preg_match_all('#<span class=acabamento-label>\s*(.*?)<span class=prazo>\(\+\s*(\d+)\s*dias?\)</span>.*?<span class=preco>\+\s*R\$\s*([\d.,]+)</span>#s', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $a) {
            $adicionais[] = ['nome' => texto_limpo($a[1]), 'dias' => (int) $a[2], 'custo' => numero_br($a[3])];
        }
    }

    // Links para outras combinações do mesmo produto
    $variacoes = [];
    if (preg_match_all("#(?:href=\"|trocarProdutoPorUrl\(')(/produto/([^\"'?]+)\?id=(\d+))#", $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $v) {
            if ($v[2] === $slug) {
                $variacoes[(int) $v[3]] = $v[1];
            }
        }
    }
    foreach ($precos as $p) {
        unset($variacoes[$p['sku']]);
    }

    return [
        'slug'           => $slug,
        'sku_atual'      => $skuAtual,
        'nome'           => $nome,
        'descricao'      => $descricao,
        'categoria'      => $categoria,
        'imagem'         => $imagem,
        'especificacoes' => $especificacoes,
        'prazo_texto'    => $prazoTexto,
        'prazo_dias'     => $prazoDias,
        'precos'         => $precos,
        'adicionais'     => $adicionais,
        'variacoes'      => $variacoes,
    ];
}
