# Catálogo de Impressos

Catálogo online e em PDF para enviar aos clientes, montado a partir dos produtos do fornecedor
(futuraim.com.br). Os preços do fornecedor são lidos automaticamente; no painel você define
margem, frete, prazos e quais produtos aparecem.

Feito em PHP + MySQL, sem dependências, para rodar em hospedagem compartilhada (Hostinger).

## O que tem

- **Catálogo online** (`/`): categorias, busca e "a partir de R$" em cada produto.
- **Página do produto**: escolha de material, formato, acabamento etc.; tabela de quantidades com o seu
  preço de venda; prazo de produção; cálculo de frete por CEP; botão **Pedir pelo WhatsApp** com o
  pedido já escrito.
- **Catálogo em PDF** (`/catalogo.php`): resumido (a partir de) ou completo (tabelas de preço),
  filtrando por categoria. Abra e use *Imprimir → Salvar como PDF*.
- **Painel** (`/admin`):
  - **Configurações**: dados da empresa, WhatsApp, logo e cor; margem padrão; arredondamento
    (,90 / inteiro); dias somados ao prazo de produção; frete padrão e frete grátis acima de um valor.
  - **Categorias**: margem própria, ordem e visibilidade.
  - **Produtos**: margem própria, visível/oculto e destaque, com o custo e o preço de venda lado a lado.
  - **Frete**: faixas de CEP com valor e prazo de entrega (ex.: cidade, estado).
  - **Sincronização**: andamento da leitura dos produtos do fornecedor.

O preço de venda é `custo do fornecedor × (1 + margem%)`, arredondado conforme a configuração.
A margem do produto vale sobre a da categoria, que vale sobre a padrão.

Os acabamentos extras (vinco, furo, corte especial) mudam de preço conforme a quantidade no
fornecedor; por isso aparecem como **sob consulta**.

## Instalação na Hostinger

1. **Banco de dados**: no hPanel, *Bancos de Dados → Gerenciamento*, crie um banco MySQL e um
   usuário. Anote nome do banco, usuário e senha.
2. **Arquivos**: envie todos os arquivos deste repositório para `public_html` (ou uma subpasta)
   pelo *Gerenciador de Arquivos* ou por Git (*Avançado → Git*).
3. **Configuração**: copie `config.sample.php` para `config.php` e preencha:
   - dados do banco (`nome`, `usuario`, `senha`; `host` normalmente `localhost`);
   - `admin_usuario` e `admin_senha` do painel;
   - `chave_cron` com um texto longo qualquer.
4. Acesse `https://seusite.com.br/admin`, entre e preencha as **Configurações** (nome, WhatsApp,
   margem, frete).
5. **Cron** (*Avançado → Cron Jobs*): crie um cron a cada 10 minutos com o comando
   ```
   /usr/bin/php /home/SEU_USUARIO/public_html/cron/sincronizar.php
   ```
   (o caminho exato aparece em *Admin → Sincronização*).

A primeira leitura completa percorre ~600 produtos e dezenas de milhares de combinações e leva
algumas horas; o cron continua sozinho de onde parou. Depois disso, os preços são atualizados a
cada 24 horas (configurável). Enquanto isso, o catálogo já mostra o que foi importado.

Dica: ocultar categorias que você não vende (em *Categorias*) deixa a sincronização bem mais rápida,
porque as variações desses produtos deixam de ser lidas.

## Teste local

```
cp config.sample.php config.php   # e troque o driver para 'sqlite'
php -S localhost:8000
php cron/sincronizar.php 60       # importa por 60 segundos
```

## Estrutura

```
index.php, produto.php, catalogo.php   páginas públicas
api/frete.php                          cálculo de frete por CEP
admin/                                 painel
cron/sincronizar.php                   sincronização (cron ou URL com chave)
src/leitor.php                         leitura das páginas do fornecedor
src/importador.php                     fila de sincronização
src/precos.php                         margem, arredondamento, prazo e frete
src/db.php                             banco (MySQL na hospedagem, SQLite para testes)
```
