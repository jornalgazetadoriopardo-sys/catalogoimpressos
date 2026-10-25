<?php
/**
 * Atualização automática dos valores (opcional).
 *
 * Os valores podem ser atualizados pelo botão "Atualizar valores" no painel.
 * Para atualizar sozinho, defina em Configurações "Atualizar valores
 * automaticamente a cada (horas)" e crie um cron a cada 15 minutos:
 *
 *   Hostinger (hPanel > Avançado > Cron Jobs):
 *     /usr/bin/php /home/SEU_USUARIO/public_html/cron/sincronizar.php
 *   ou por URL:
 *     https://seusite.com.br/cron/sincronizar.php?chave=SUA_CHAVE_CRON
 *
 * Também termina leituras que ficaram pela metade (página do painel fechada).
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

$trava = fopen(RAIZ . '/data/sincronizar.lock', 'c');
if (!$trava || !flock($trava, LOCK_EX | LOCK_NB)) {
    exit("Outra leitura já está em andamento.\n");
}

$log = function (string $msg) { echo date('H:i:s') . "  $msg\n"; };

if (!fila_ativa()) {
    $horas = (int) cfg('atualizacao_auto_horas');
    $ultima = cfg('ultima_atualizacao');
    if ($horas <= 0) {
        $log('Atualização automática desligada (Configurações > Atualização de valores).');
        exit;
    }
    if ($ultima !== '' && strtotime($ultima) + $horas * 3600 > time()) {
        $log('Nada a fazer. Próxima atualização após ' . date('d/m/Y H:i', strtotime($ultima) + $horas * 3600) . '.');
        exit;
    }
    $log('Iniciando atualização: ' . iniciar_atualizacao() . ' opções de produtos.');
}

$r = processar_fila($segundos, $log);
$log(sprintf('Fila: %d pendentes, %d lidas, %d com erro.%s', $r['pendente'], $r['ok'], $r['erro'], $r['em_andamento'] ? '' : ' Concluído.'));
