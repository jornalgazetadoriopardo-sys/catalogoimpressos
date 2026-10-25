<?php
/**
 * Chamadas do painel para ler o site da gráfica. Cada chamada trabalha ~20 s
 * e devolve o andamento; o navegador repete até a fila acabar.
 *   acao=adicionar  itens[]=slug ou link   → cadastra produtos com todas as opções
 *   acao=atualizar  slugs[]=… (opcional)   → relê os preços dos produtos cadastrados
 *   acao=continuar                          → só processa o que já está na fila
 */
declare(strict_types=1);

require __DIR__ . '/_base.php';
require RAIZ . '/src/importador.php';

header('Content-Type: application/json; charset=utf-8');
if (!admin_logado() || !post_valido()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'erro' => 'Sessão expirada. Entre novamente.']));
}
session_write_close();
@set_time_limit(90);
ignore_user_abort(true);

$log = [];
try {
    switch ($_POST['acao'] ?? '') {
        case 'adicionar':
            $n = adicionar_produtos((array) ($_POST['itens'] ?? []));
            $log[] = "$n produto(s) na fila.";
            break;
        case 'atualizar':
            $n = iniciar_atualizacao(array_values(array_filter((array) ($_POST['slugs'] ?? []), 'is_string')));
            $log[] = "$n opção(ões) para atualizar.";
            break;
    }
    $r = processar_fila(20, function ($m) use (&$log) { $log[] = $m; });
    $r['produtos'] = (int) db_valor('SELECT COUNT(*) FROM produtos');
    echo json_encode(['ok' => true, 'resumo' => $r, 'log' => $log], JSON_UNESCAPED_UNICODE);
} catch (Throwable $ex) {
    echo json_encode(['ok' => false, 'erro' => $ex->getMessage(), 'log' => $log], JSON_UNESCAPED_UNICODE);
}
