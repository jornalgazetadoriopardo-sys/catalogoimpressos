<?php
/**
 * Sincronização automática dos produtos.
 *
 * Hostinger (hPanel > Avançado > Cron Jobs), a cada 10 minutos:
 *   /usr/bin/php /home/SEU_USUARIO/public_html/cron/sincronizar.php
 *
 * Ou, se preferir por URL:
 *   https://seusite.com.br/cron/sincronizar.php?chave=SUA_CHAVE_CRON
 *
 * Cada execução trabalha por um tempo limitado e continua de onde parou na
 * próxima. Quando a fila termina, uma nova sincronização só começa depois do
 * intervalo configurado no admin (padrão: 24 horas).
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
require RAIZ . '/src/importador.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    if (!hash_equals((string) config('chave_cron'), (string) ($_GET['chave'] ?? ''))) {
        http_response_code(403);
        exit('Chave inválida.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$segundos = $cli ? (int) ($argv[1] ?? 240) : 50;
@set_time_limit($segundos + 60);
ignore_user_abort(true);

// Evita duas execuções simultâneas
$trava = fopen(RAIZ . '/data/sincronizar.lock', 'c');
if (!$trava || !flock($trava, LOCK_EX | LOCK_NB)) {
    exit("Outra sincronização já está rodando.\n");
}

$log = function (string $msg) { echo date('H:i:s') . "  $msg\n"; };

if (!sincronizacao_em_andamento()) {
    $ultimoFim = cfg('sync_fim');
    $intervalo = max(1, (int) cfg('intervalo_sync_horas')) * 3600;
    if ($ultimoFim === '' || strtotime($ultimoFim) + $intervalo <= time()) {
        $log('Iniciando nova sincronização: ' . iniciar_sincronizacao() . ' produtos no sitemap.');
    } else {
        $log('Nada a fazer. Próxima sincronização após ' . date('d/m/Y H:i', strtotime($ultimoFim) + $intervalo) . '.');
        exit;
    }
}

$r = processar_fila($segundos, $log);
$log(sprintf('Fila: %d pendentes, %d concluídas, %d com erro.%s',
    $r['pendente'], $r['ok'], $r['erro'], $r['em_andamento'] ? '' : ' Sincronização concluída.'));
