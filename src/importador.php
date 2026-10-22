<?php
declare(strict_types=1);

require_once __DIR__ . '/leitor.php';

/**
 * Sincronização em etapas: iniciar_sincronizacao() monta a fila a partir do
 * sitemap e processar_fila() consome a fila por um tempo limitado, para caber
 * nos limites de execução da hospedagem compartilhada. Pode ser chamada
 * repetidamente (cron ou botão no admin) até a fila esvaziar.
 */

function sincronizacao_em_andamento(): bool
{
    return cfg('sync_inicio') !== '' && cfg('sync_fim') === '';
}

function iniciar_sincronizacao(): int
{
    $urls = urls_do_sitemap(rtrim(config('origem'), '/'));
    if (!$urls) {
        throw new RuntimeException('Nenhum produto encontrado no sitemap.');
    }
    db_exec('DELETE FROM fila');
    $agora = agora();
    db()->beginTransaction();
    foreach ($urls as $u) {
        db_upsert('fila', ['url' => $u, 'status' => 'pendente', 'tentativas' => 0, 'erro' => null, 'atualizado_em' => $agora], ['url']);
    }
    db()->commit();
    salvar_cfg('sync_inicio', $agora);
    salvar_cfg('sync_fim', '');
    return count($urls);
}

/** Processa itens da fila por até $segundos. Retorna o resumo da fila. */
function processar_fila(int $segundos, ?callable $log = null): array
{
    $limite = microtime(true) + $segundos;
    $atraso = max(0, (int) cfg('atraso_requisicao_ms')) * 1000;
    $paralelo = min(8, max(1, (int) cfg('requisicoes_paralelas')));
    $inicio = cfg('sync_inicio');

    while (microtime(true) < $limite) {
        $itens = db_todos("SELECT url, tentativas FROM fila WHERE status = 'pendente' ORDER BY atualizado_em, url LIMIT $paralelo");
        if (!$itens) {
            finalizar_sincronizacao($inicio);
            break;
        }
        $paginas = baixar_varios(array_column($itens, 'url'));
        foreach ($itens as $item) {
            $url = $item['url'];
            try {
                if ($paginas[$url] instanceof Throwable) {
                    throw $paginas[$url];
                }
                $dados = ler_pagina_produto($paginas[$url], $url);
                $novas = salvar_pagina($dados, $inicio);
                db_exec("UPDATE fila SET status = 'ok', atualizado_em = ? WHERE url = ?", [agora(), $url]);
                if ($log) {
                    $log(sprintf('ok  %s  (%d preços, %d novas variações)', $url, count($dados['precos']), $novas));
                }
            } catch (Throwable $ex) {
                $tent = (int) $item['tentativas'] + 1;
                db_exec('UPDATE fila SET status = ?, tentativas = ?, erro = ?, atualizado_em = ? WHERE url = ?', [
                    $tent >= 3 ? 'erro' : 'pendente', $tent, mb_substr($ex->getMessage(), 0, 250), agora(), $url,
                ]);
                if ($log) {
                    $log('ERRO ' . $url . ' — ' . $ex->getMessage());
                }
            }
        }
        usleep($atraso);
    }
    return resumo_fila();
}

/** Grava a página lida e retorna quantas variações novas foram enfileiradas. */
function salvar_pagina(array $d, string $inicio): int
{
    if ($d['slug'] === '' || !$d['precos']) {
        return 0; // página sem preço (ex.: produto sob orçamento)
    }
    $novas = 0;
    $agora = agora();
    db()->beginTransaction();
    try {
        if ($d['categoria']) {
            db_upsert('categorias', ['slug' => $d['categoria']['slug'], 'nome' => $d['categoria']['nome']], ['slug']);
        }
        $produto = [
            'slug'          => $d['slug'],
            'nome'          => $d['nome'],
            'categoria_slug'=> $d['categoria']['slug'] ?? null,
            'ativo_origem'  => 1,
            'atualizado_em' => $agora,
        ];
        // A descrição e a imagem principal vêm da primeira página lida do produto
        $existe = db_um('SELECT atualizado_em FROM produtos WHERE slug = ?', [$d['slug']]);
        if (!$existe || $existe['atualizado_em'] < $inicio) {
            $produto['descricao'] = $d['descricao'];
            $produto['imagem'] = $d['imagem'];
        }
        db_upsert('produtos', $produto, ['slug']);

        // A chave usa os atributos em ordem fixa; a gravação mantém a ordem do site
        $ordenadas = $d['especificacoes'];
        ksort($ordenadas);
        $chave = md5($d['slug'] . '|' . json_encode($ordenadas, JSON_UNESCAPED_UNICODE));
        db_upsert('combinacoes', [
            'produto_slug'  => $d['slug'],
            'chave'         => $chave,
            'especificacoes'=> json_encode($d['especificacoes'], JSON_UNESCAPED_UNICODE),
            'prazo_dias'    => $d['prazo_dias'],
            'prazo_texto'   => $d['prazo_texto'],
            'imagem'        => $d['imagem'],
            'adicionais'    => json_encode($d['adicionais'], JSON_UNESCAPED_UNICODE),
            'atualizado_em' => $agora,
        ], ['chave']);
        $combId = (int) db_valor('SELECT id FROM combinacoes WHERE chave = ?', [$chave]);

        foreach ($d['precos'] as $p) {
            db_upsert('precos', [
                'sku'           => $p['sku'],
                'combinacao_id' => $combId,
                'quantidade'    => $p['quantidade'],
                'unidade'       => $p['unidade'],
                'custo'         => $p['custo'],
                'atualizado_em' => $agora,
            ], ['sku']);
        }

        // Enfileira combinações ainda não lidas nesta sincronização. Produtos ou
        // categorias ocultos no admin não têm as variações lidas (economiza tempo).
        $oculto = db_valor('SELECT 1 FROM produtos p LEFT JOIN categorias c ON c.slug = p.categoria_slug
                            WHERE p.slug = ? AND (p.visivel = 0 OR c.visivel = 0)', [$d['slug']]);
        foreach ($oculto ? [] : $d['variacoes'] as $sku => $caminho) {
            $lido = db_valor('SELECT 1 FROM precos WHERE sku = ? AND atualizado_em >= ?', [$sku, $inicio]);
            if (!$lido) {
                $url = rtrim(config('origem'), '/') . $caminho;
                if (!db_valor('SELECT 1 FROM fila WHERE url = ?', [$url])) {
                    db_upsert('fila', ['url' => $url, 'status' => 'pendente', 'tentativas' => 0, 'erro' => null, 'atualizado_em' => $agora], ['url']);
                    $novas++;
                }
            }
        }
        db()->commit();
        return $novas;
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
}

/** Ao fim da fila: remove o que sumiu do site de origem. */
function finalizar_sincronizacao(string $inicio): void
{
    if (!sincronizacao_em_andamento()) {
        return;
    }
    // Se alguma página falhou, mantém os dados antigos para não sumir produto por erro de rede
    $erros = (int) db_valor("SELECT COUNT(*) FROM fila WHERE status = 'erro'");
    if ($erros === 0) {
        db_exec('DELETE FROM precos WHERE atualizado_em < ?', [$inicio]);
        db_exec('DELETE FROM combinacoes WHERE id NOT IN (SELECT DISTINCT combinacao_id FROM precos)');
        db_exec('UPDATE produtos SET ativo_origem = 0 WHERE atualizado_em < ?', [$inicio]);
    }
    salvar_cfg('sync_fim', agora());
}

function resumo_fila(): array
{
    $r = ['pendente' => 0, 'ok' => 0, 'erro' => 0];
    foreach (db_todos('SELECT status, COUNT(*) n FROM fila GROUP BY status') as $l) {
        $r[$l['status']] = (int) $l['n'];
    }
    $r['em_andamento'] = sincronizacao_em_andamento();
    $r['inicio'] = cfg('sync_inicio');
    $r['fim'] = cfg('sync_fim');
    return $r;
}
