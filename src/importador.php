<?php
declare(strict_types=1);

require_once __DIR__ . '/leitor.php';

/**
 * Fila de leitura do site da gráfica, processada aos poucos (botão no admin
 * ou cron) para caber nos limites da hospedagem compartilhada.
 *
 * - adicionar_produtos(): lê a página do produto e segue os links para todas
 *   as combinações dele (material, formato, acabamento…).
 * - iniciar_atualizacao(): relê uma página por combinação já cadastrada e
 *   atualiza os preços; não procura combinações novas.
 */

function fila_ativa(): bool
{
    return (bool) db_valor("SELECT 1 FROM fila WHERE status = 'pendente' LIMIT 1");
}

function enfileirar(string $url, bool $descobrir): bool
{
    if (db_valor("SELECT 1 FROM fila WHERE url = ? AND status = 'pendente'", [$url])) {
        return false;
    }
    db_upsert('fila', [
        'url' => $url, 'status' => 'pendente', 'tentativas' => 0, 'erro' => null,
        'descobrir' => $descobrir ? 1 : 0, 'atualizado_em' => agora(),
    ], ['url']);
    return true;
}

/** Começa um trabalho novo se a fila estiver vazia (limpa o histórico anterior). */
function abrir_trabalho(string $tipo): void
{
    if (fila_ativa()) {
        return;
    }
    db_exec('DELETE FROM fila');
    salvar_cfg('trabalho_tipo', $tipo);
    salvar_cfg('trabalho_inicio', agora());
    salvar_cfg('trabalho_fim', '');
}

/** Aceita slugs ou links de produto do site. Retorna quantos foram enfileirados. */
function adicionar_produtos(array $itens): int
{
    $origem = rtrim(config('origem'), '/');
    $slugs = [];
    foreach ($itens as $i) {
        $s = slug_da_url((string) $i) ?: preg_replace('/[^a-z0-9-]/', '', strtolower(trim((string) $i)));
        if ($s !== '') {
            $slugs[$s] = true;
        }
    }
    if (!$slugs) {
        return 0;
    }
    abrir_trabalho('adicionar');
    $n = 0;
    foreach (array_keys($slugs) as $s) {
        $n += enfileirar("$origem/produto/$s", true) ? 1 : 0;
    }
    return $n;
}

/** Enfileira uma página por combinação cadastrada (de todos ou de alguns produtos). */
function iniciar_atualizacao(array $slugs = []): int
{
    $origem = rtrim(config('origem'), '/');
    $where = '';
    if ($slugs) {
        $where = 'WHERE c.produto_slug IN (' . implode(',', array_fill(0, count($slugs), '?')) . ')';
    }
    $linhas = db_todos(
        "SELECT c.produto_slug, MIN(p.sku) sku FROM combinacoes c JOIN precos p ON p.combinacao_id = c.id
         $where GROUP BY c.id, c.produto_slug",
        $slugs
    );
    abrir_trabalho('atualizar');
    $n = 0;
    foreach ($linhas as $l) {
        $n += enfileirar("$origem/produto/{$l['produto_slug']}?id={$l['sku']}", false) ? 1 : 0;
    }
    return $n;
}

/** Processa itens da fila por até $segundos. Retorna o resumo da fila. */
function processar_fila(int $segundos, ?callable $log = null): array
{
    $limite = microtime(true) + $segundos;
    $atraso = max(0, (int) cfg('atraso_requisicao_ms')) * 1000;
    $paralelo = min(8, max(1, (int) cfg('requisicoes_paralelas')));
    $inicio = cfg('trabalho_inicio');

    while (microtime(true) < $limite) {
        $itens = db_todos("SELECT url, tentativas, descobrir FROM fila WHERE status = 'pendente' ORDER BY atualizado_em, url LIMIT $paralelo");
        if (!$itens) {
            finalizar_trabalho();
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
                if (!$dados['precos']) {
                    throw new RuntimeException('Página sem tabela de preços (produto sob orçamento ou indisponível).');
                }
                $novas = salvar_pagina($dados, $inicio, (bool) $item['descobrir']);
                db_exec("UPDATE fila SET status = 'ok', atualizado_em = ? WHERE url = ?", [agora(), $url]);
                if ($log) {
                    $log(sprintf('%s — %s%s', $dados['nome'], implode(' · ', $dados['especificacoes']) ?: 'opção padrão',
                        $novas ? " (+$novas opções)" : ''));
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

/** Grava a página lida e retorna quantas combinações novas foram enfileiradas. */
function salvar_pagina(array $d, string $inicio, bool $descobrir): int
{
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
            'atualizado_em' => $agora,
        ];
        // Descrição e imagem principal vêm da página principal do produto
        $existe = db_um('SELECT slug, descricao FROM produtos WHERE slug = ?', [$d['slug']]);
        if (!$existe || ($descobrir && !str_contains($d['url'] ?? '', '?id=')) || (string) $existe['descricao'] === '') {
            $produto['descricao'] = $d['descricao'];
            $produto['imagem'] = $d['imagem'];
        }
        if (!$existe) {
            $produto['adicionado_em'] = $agora;
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

        // Quantidades que sumiram desta combinação no site deixam de valer
        $skus = array_column($d['precos'], 'sku');
        db_exec('DELETE FROM precos WHERE combinacao_id = ? AND sku NOT IN (' . implode(',', array_map('intval', $skus)) . ')', [$combId]);
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

        if ($descobrir) {
            $origem = rtrim(config('origem'), '/');
            foreach ($d['variacoes'] as $sku => $caminho) {
                if (!db_valor('SELECT 1 FROM precos WHERE sku = ? AND atualizado_em >= ?', [$sku, $inicio])
                    && !db_valor('SELECT 1 FROM fila WHERE url = ?', [$origem . $caminho])) {
                    $novas += enfileirar($origem . $caminho, true) ? 1 : 0;
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

/**
 * Fim da fila. Numa atualização sem erros, combinações que não foram relidas
 * (o site mudou as opções) são removidas; com erros, nada é apagado.
 */
function finalizar_trabalho(): void
{
    if (cfg('trabalho_fim') !== '') {
        return; // já finalizado
    }
    $inicio = cfg('trabalho_inicio');
    $erros = (int) db_valor("SELECT COUNT(*) FROM fila WHERE status = 'erro'");
    if (cfg('trabalho_tipo') === 'atualizar' && $erros === 0 && $inicio !== '') {
        // só nos produtos que participaram desta atualização
        $slugs = array_column(db_todos('SELECT DISTINCT produto_slug FROM combinacoes WHERE atualizado_em >= ?', [$inicio]), 'produto_slug');
        foreach ($slugs as $slug) {
            $velhas = array_map('intval', array_column(db_todos('SELECT id FROM combinacoes WHERE produto_slug = ? AND atualizado_em < ?', [$slug, $inicio]), 'id'));
            if ($velhas) {
                db_exec('DELETE FROM precos WHERE combinacao_id IN (' . implode(',', $velhas) . ')');
                db_exec('DELETE FROM combinacoes WHERE id IN (' . implode(',', $velhas) . ')');
            }
        }
    }
    db_exec('DELETE FROM combinacoes WHERE id NOT IN (SELECT DISTINCT combinacao_id FROM precos)');
    salvar_cfg('trabalho_fim', agora());
    if (cfg('trabalho_tipo') === 'atualizar') {
        salvar_cfg('ultima_atualizacao', agora());
    }
}

function resumo_fila(): array
{
    $r = ['pendente' => 0, 'ok' => 0, 'erro' => 0];
    foreach (db_todos('SELECT status, COUNT(*) n FROM fila GROUP BY status') as $l) {
        $r[$l['status']] = (int) $l['n'];
    }
    $r['em_andamento'] = $r['pendente'] > 0;
    $r['tipo'] = cfg('trabalho_tipo');
    return $r;
}

/** Remove um produto e tudo que pertence a ele. */
function remover_produto(string $slug): void
{
    db_exec('DELETE FROM precos WHERE combinacao_id IN (SELECT id FROM combinacoes WHERE produto_slug = ?)', [$slug]);
    db_exec('DELETE FROM combinacoes WHERE produto_slug = ?', [$slug]);
    db_exec('DELETE FROM produtos WHERE slug = ?', [$slug]);
}
