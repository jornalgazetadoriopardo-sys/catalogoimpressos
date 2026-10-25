<?php
declare(strict_types=1);

const ESQUEMA_VERSAO = 2;

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $c = config('db');
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['arquivo']);
        $pdo->exec('PRAGMA journal_mode = WAL');
    } else {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['host'], $c['nome']);
        $pdo = new PDO($dsn, $c['usuario'], $c['senha']);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Cria as tabelas na primeira execução (e quando a versão do esquema mudar)
    $marca = RAIZ . '/data/esquema-v' . ESQUEMA_VERSAO;
    if (!is_file($marca)) {
        db_migrar($pdo);
        @file_put_contents($marca, date('c'));
    }
    return $pdo;
}

function db_sqlite(): bool
{
    return (config('db')['driver'] ?? 'mysql') === 'sqlite';
}

function db_exec(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_todos(string $sql, array $params = []): array
{
    return db_exec($sql, $params)->fetchAll();
}

function db_um(string $sql, array $params = []): ?array
{
    $linha = db_exec($sql, $params)->fetch();
    return $linha ?: null;
}

function db_valor(string $sql, array $params = [])
{
    $v = db_exec($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/**
 * INSERT ou UPDATE pela chave. Só as colunas em $dados são atualizadas,
 * então campos editados no admin (margem, visível...) não são sobrescritos.
 */
function db_upsert(string $tabela, array $dados, array $chaves): void
{
    $cols = array_keys($dados);
    $marc = array_map(fn($c) => ':' . $c, $cols);
    $atualizar = array_diff($cols, $chaves);
    $sql = "INSERT INTO $tabela (" . implode(',', $cols) . ') VALUES (' . implode(',', $marc) . ')';
    if (db_sqlite()) {
        $sql .= ' ON CONFLICT(' . implode(',', $chaves) . ') DO ' . ($atualizar
            ? 'UPDATE SET ' . implode(',', array_map(fn($c) => "$c = excluded.$c", $atualizar))
            : 'NOTHING');
    } else {
        $alvo = $atualizar ?: [$chaves[0]];
        $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', array_map(fn($c) => "$c = VALUES($c)", $alvo));
    }
    db_exec($sql, array_combine($marc, array_values($dados)));
}

function db_migrar(PDO $pdo): void
{
    $sqlite = db_sqlite();
    $auto = $sqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $fim = $sqlite ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $tabelas = [
        "CREATE TABLE IF NOT EXISTS configuracoes (
            chave VARCHAR(64) PRIMARY KEY,
            valor MEDIUMTEXT
        )$fim",
        "CREATE TABLE IF NOT EXISTS categorias (
            slug VARCHAR(190) PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            margem DECIMAL(6,2) NULL,
            visivel TINYINT NOT NULL DEFAULT 1,
            ordem INT NOT NULL DEFAULT 0
        )$fim",
        "CREATE TABLE IF NOT EXISTS produtos (
            slug VARCHAR(190) PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            categoria_slug VARCHAR(190) NULL,
            descricao MEDIUMTEXT,
            imagem VARCHAR(600),
            margem DECIMAL(6,2) NULL,
            visivel TINYINT NOT NULL DEFAULT 1,
            destaque TINYINT NOT NULL DEFAULT 0,
            ativo_origem TINYINT NOT NULL DEFAULT 1,
            adicionado_em VARCHAR(19),
            atualizado_em VARCHAR(19)
        )$fim",
        "CREATE TABLE IF NOT EXISTS combinacoes (
            id $auto,
            produto_slug VARCHAR(190) NOT NULL,
            chave CHAR(32) NOT NULL UNIQUE,
            especificacoes MEDIUMTEXT,
            prazo_dias INT NOT NULL DEFAULT 0,
            prazo_texto VARCHAR(120),
            imagem VARCHAR(600),
            adicionais MEDIUMTEXT,
            atualizado_em VARCHAR(19)
        )$fim",
        "CREATE TABLE IF NOT EXISTS precos (
            sku INT PRIMARY KEY,
            combinacao_id INT NOT NULL,
            quantidade INT NOT NULL,
            unidade VARCHAR(30),
            custo DECIMAL(12,2) NOT NULL,
            atualizado_em VARCHAR(19)
        )$fim",
        "CREATE TABLE IF NOT EXISTS fila (
            url VARCHAR(255) PRIMARY KEY,
            status VARCHAR(10) NOT NULL DEFAULT 'pendente',
            tentativas INT NOT NULL DEFAULT 0,
            erro VARCHAR(255),
            descobrir TINYINT NOT NULL DEFAULT 1,
            atualizado_em VARCHAR(19)
        )$fim",
        "CREATE TABLE IF NOT EXISTS frete_regioes (
            id $auto,
            nome VARCHAR(120) NOT NULL,
            cep_inicio CHAR(8) NOT NULL,
            cep_fim CHAR(8) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            prazo_dias INT NOT NULL DEFAULT 0
        )$fim",
        "CREATE TABLE IF NOT EXISTS orcamentos (
            id $auto,
            token CHAR(32) NOT NULL UNIQUE,
            cliente_nome VARCHAR(160) NOT NULL,
            cliente_telefone VARCHAR(40),
            cliente_email VARCHAR(160),
            cliente_documento VARCHAR(40),
            cliente_cep VARCHAR(9),
            frete_descricao VARCHAR(160),
            frete_valor DECIMAL(10,2) NOT NULL DEFAULT 0,
            desconto DECIMAL(10,2) NOT NULL DEFAULT 0,
            validade_dias INT NOT NULL DEFAULT 7,
            prazo_entrega VARCHAR(160),
            pagamento VARCHAR(255),
            observacoes MEDIUMTEXT,
            criado_em VARCHAR(19),
            atualizado_em VARCHAR(19)
        )$fim",
        "CREATE TABLE IF NOT EXISTS orcamento_itens (
            id $auto,
            orcamento_id INT NOT NULL,
            ordem INT NOT NULL DEFAULT 0,
            produto_slug VARCHAR(190),
            imagem VARCHAR(600),
            titulo VARCHAR(255) NOT NULL,
            detalhes MEDIUMTEXT,
            quantidade INT NOT NULL DEFAULT 1,
            unidade VARCHAR(30),
            valor DECIMAL(12,2) NOT NULL DEFAULT 0,
            prazo_dias INT NOT NULL DEFAULT 0
        )$fim",
    ];
    foreach ($tabelas as $sql) {
        $pdo->exec($sql);
    }
    // Colunas criadas depois da primeira versão
    $colunas = [
        'produtos.adicionado_em' => 'VARCHAR(19)',
        'fila.descobrir'         => 'TINYINT NOT NULL DEFAULT 1',
    ];
    foreach ($colunas as $alvo => $tipo) {
        [$tabela, $coluna] = explode('.', $alvo);
        try {
            $pdo->query("SELECT $coluna FROM $tabela LIMIT 1");
        } catch (PDOException $ex) {
            $pdo->exec("ALTER TABLE $tabela ADD COLUMN $coluna $tipo");
        }
    }
    $indices = [
        'idx_comb_produto' => 'combinacoes(produto_slug)',
        'idx_precos_comb'  => 'precos(combinacao_id)',
        'idx_prod_cat'     => 'produtos(categoria_slug)',
        'idx_fila_status'  => 'fila(status)',
        'idx_itens_orc'    => 'orcamento_itens(orcamento_id)',
    ];
    foreach ($indices as $nome => $alvo) {
        try {
            $pdo->exec(($sqlite ? 'CREATE INDEX IF NOT EXISTS ' : 'CREATE INDEX ') . "$nome ON $alvo");
        } catch (PDOException $ex) {
            // índice já existe (MySQL não tem IF NOT EXISTS para índices)
        }
    }
}
