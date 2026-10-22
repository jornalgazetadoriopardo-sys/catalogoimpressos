<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$frete = calcular_frete((string) ($_GET['cep'] ?? ''), (float) ($_GET['subtotal'] ?? 0));
echo json_encode($frete ?? ['erro' => 'CEP inválido.'], JSON_UNESCAPED_UNICODE);
