# Catálogo de Impressos

Catálogo online para os clientes, orçamentos em PDF e painel de gestão, usando os produtos e
preços do site da gráfica fornecedora (futuraim.com.br). Você escolhe no painel quais produtos
entram, define margem, frete e prazos, e atualiza os valores com um botão.

Feito em PHP + MySQL, sem instalar nada além disso: roda no XAMPP e na hospedagem compartilhada
(Hostinger).

## Como funciona

**Painel** (`/admin`):

| Tela | Para quê |
|---|---|
| **Início** | Atalhos: novo orçamento, adicionar produtos, atualizar valores, ver catálogo. |
| **Orçamentos** | Lista, busca, editar, duplicar. Cada orçamento tem um link para o cliente baixar o PDF. |
| **Produtos** | Produtos cadastrados: margem própria, mostrar/ocultar, destaque, remover. Botão **↻ Atualizar valores** (todos ou os selecionados). |
| **Adicionar produtos** | Navega pelas categorias do site da gráfica (ou busca, ou cola o link) e adiciona. Todas as opções do produto (material, formato, acabamento, quantidades) são lidas e cadastradas. |
| **Configurações** | Empresa, logo, WhatsApp, margem padrão, arredondamento, prazo extra, frete, textos padrão do orçamento. Abas para frete por faixa de CEP e margem por categoria. |

**Para o cliente:**

- **Catálogo** (`/`): categorias, busca, preço "a partir de"; na página do produto o cliente escolhe as
  opções e a quantidade, calcula o frete pelo CEP e pede pelo WhatsApp.
- **Catálogo em PDF** (`/catalogo.php`): resumido ou com tabelas de preço.
- **Orçamento** (`/orcamento.php?t=…`): página do orçamento com botão **Baixar PDF**. No painel há um
  botão para enviar o link pelo WhatsApp do cliente.

**Preço de venda** = custo do fornecedor × (1 + margem %), arredondado conforme a configuração.
A margem do produto vale sobre a da categoria, que vale sobre a padrão.

Acabamentos extras (vinco, furo, corte especial) aparecem como *sob consulta*: no site da gráfica o
preço deles muda com a quantidade.

## Testar no computador (XAMPP)

1. Copie a pasta do projeto para `C:\xampp\htdocs\catalogoimpressos`
   (baixe o ZIP do GitHub ou use `git clone`).
2. No **XAMPP Control Panel**, inicie **Apache** e **MySQL**.
3. Abra http://localhost/phpmyadmin → **Novo** → crie o banco `catalogo` (agrupamento `utf8mb4_unicode_ci`).
4. Na pasta do projeto, copie `config.sample.php` para `config.php` e ajuste:
   ```php
   'nome'    => 'catalogo',
   'usuario' => 'root',
   'senha'   => '',
   ```
   e defina `admin_usuario` / `admin_senha`.
5. Acesse http://localhost/catalogoimpressos/admin, entre e:
   - em **Configurações**, preencha empresa, logo, WhatsApp e margem;
   - em **Adicionar produtos**, escolha alguns produtos;
   - em **Orçamentos**, crie um orçamento e clique em **Ver / Baixar PDF**.
6. O catálogo fica em http://localhost/catalogoimpressos.

As tabelas do banco são criadas sozinhas no primeiro acesso.

Se aparecer erro de certificado SSL ao adicionar produtos, o projeto já traz o arquivo
`src/cacert.pem` e o usa automaticamente quando o PHP não tem certificados configurados.
Confira também se as extensões `curl` e `gd` estão ativas no `php.ini` do XAMPP (linhas
`extension=curl` e `extension=gd` sem `;` na frente) e reinicie o Apache.

## Publicar na Hostinger

1. Crie um banco MySQL em *hPanel → Bancos de Dados*.
2. Envie os arquivos para `public_html` (Gerenciador de Arquivos ou *Avançado → Git*).
3. Crie o `config.php` (como acima, com os dados do banco da Hostinger).
4. Acesse `https://seusite.com.br/admin`.

**Atualização automática (opcional):** em *Configurações → Atualização de valores*, defina de quantas
em quantas horas atualizar e crie um cron (*hPanel → Avançado → Cron Jobs*) a cada 15 minutos:
```
/usr/bin/php /home/SEU_USUARIO/public_html/cron/sincronizar.php
```
Sem isso, use o botão **↻ Atualizar valores** no painel.

## Estrutura

```
index.php, produto.php, catalogo.php   catálogo público
orcamento.php                          orçamento para o cliente (PDF)
img.php                                fotos dos produtos (cache local, reduzidas)
api/frete.php                          frete por CEP
admin/                                 painel
cron/sincronizar.php                   atualização automática (opcional)
src/leitor.php                         leitura das páginas do site da gráfica
src/importador.php                     fila: adicionar produtos e atualizar valores
src/precos.php                         margem, arredondamento, prazo e frete
src/orcamentos.php                     orçamentos
src/db.php                             banco (MySQL; SQLite para testes)
assets/vendor/html2pdf.bundle.min.js   geração do PDF no navegador (MIT)
```
