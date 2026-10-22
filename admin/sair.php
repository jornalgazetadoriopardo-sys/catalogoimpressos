<?php
declare(strict_types=1);

require __DIR__ . '/_base.php';

$_SESSION = [];
session_destroy();
voltar('index.php');
