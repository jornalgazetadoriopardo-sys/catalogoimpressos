<?php
// Copie este arquivo para config.php e preencha com os dados da sua hospedagem.
return [
    // Banco de dados
    //   Hostinger: hPanel > Bancos de Dados > MySQL (nome e usuário começam com u000…)
    //   XAMPP:     nome 'catalogo', usuario 'root', senha '' (vazia)
    'db' => [
        'driver'  => 'mysql',          // 'mysql' na hospedagem; 'sqlite' para testes locais
        'host'    => 'localhost',
        'nome'    => 'u000000000_catalogo',
        'usuario' => 'u000000000_catalogo',
        'senha'   => 'troque-esta-senha',
        'arquivo' => __DIR__ . '/data/catalogo.sqlite', // usado só com sqlite
    ],

    // Acesso ao painel /admin
    'admin_usuario' => 'admin',
    'admin_senha'   => 'troque-esta-senha',

    // Chave para a atualização automática de valores por URL (cron via wget/curl),
    // caso o cron da hospedagem não rode PHP pela linha de comando.
    'chave_cron' => 'troque-por-um-texto-longo-e-aleatorio',

    // Site de origem dos produtos
    'origem' => 'https://www.futuraim.com.br',
];
