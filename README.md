# Chico Prince

Aplicação de catálogo de produtos desenvolvida para um teste técnico Full Stack,
com API Laravel, banco MySQL e interface Vue 3 que consome a própria API.

## Estado do projeto

A base de desenvolvimento está funcional: Laravel e MySQL executam em containers,
as migrations iniciais estão aplicadas e o Vue consulta a saúde da API e do banco.
O modelo e os dados de exemplo estão disponíveis. O contrato REST está documentado;
a autenticação por sessão e o CRUD da API estão disponíveis. O catálogo público
oferece busca, filtros combinados, ordenação, paginação e detalhes dos produtos.
Login, logout, criação, edição e exclusão estão disponíveis na interface.

## Stack e arquitetura

| Camada | Tecnologia |
|---|---|
| API | PHP 8.4 / Laravel 12 |
| Banco de dados | MySQL 8.4 |
| Interface | Vue 3 / TypeScript / Vite |
| Ambiente de desenvolvimento | Docker Compose / Laravel Sail / Node 24 |

Versões verificadas: PHP 8.4.26, Laravel 12.69.3, Sail 1.68.0,
Node 24.21.0, Vue 3.5.43 e Vite 8.3.4. Os locks do Composer e npm
registram as dependências; as tags das imagens podem receber atualizações.

```text
Navegador → Vue/Vite (:5173) → /api → Laravel (:80 interno) → MySQL (:3306 interno)
```

- [backend/](./backend/): API, migrations, testes e configuração do Sail.
- [frontend/](./frontend/): aplicação Vue com Composition API e componentes TypeScript.
- [backend/compose.yaml](./backend/compose.yaml): orquestra os três serviços.

| Serviço | Acesso local | Persistência |
|---|---|---|
| `laravel.test` | http://localhost:8081 | Código compartilhado com o host |
| `frontend` | http://localhost:5173 | Código compartilhado; dependências em volume nomeado |
| `mysql` | Somente pela rede interna | Volume nomeado `sail-mysql` |

Laravel acessa o banco pelo nome `mysql`. O healthcheck do banco executa uma consulta
com o usuário da aplicação antes de liberar a inicialização do backend.
As portas publicadas ficam restritas ao loopback.

## Executar em desenvolvimento

### Pré-requisitos

- Git e Docker com Compose, usando containers Linux.
- No Windows: Docker Desktop com WSL2 habilitado.
- Portas 8081 e 5173 disponíveis e espaço para imagens e volumes Docker.

PHP, Composer e Node não precisam estar instalados no host.
Execute os comandos na raiz do repositório, após cloná-lo.

### 1. Preparar o backend

O comando abaixo funciona no PowerShell e em shells POSIX, usando o diretório atual:

```sh
docker run --rm --mount "type=bind,source=${PWD}/backend,target=/var/www/html" -w /var/www/html laravelsail/php84-composer:latest sh -lc 'php scripts/init-env.php && composer install --no-interaction'
```

O [script de ambiente](./backend/scripts/init-env.php) cria o arquivo local de
configuração a partir de [.env.example](./backend/.env.example), com chave da aplicação
e senhas aleatórias. Se ele já existe, seus valores são preservados; a senha de
demonstração é adicionada somente quando estiver ausente ou vazia.
Nenhuma credencial precisa ser incluída no repositório.

### 2. Construir e iniciar os serviços

```sh
docker compose -f backend/compose.yaml config --quiet
docker compose -f backend/compose.yaml up -d --build
```

O primeiro build do Sail pode levar alguns minutos. O frontend executa `npm ci`
ao iniciar, usando o lock versionado.

### 3. Ajustar permissões e executar migrations

O bootstrap roda como `root`, enquanto o servidor PHP usa o usuário `sail`.
Ajuste a propriedade apenas da configuração local e dos diretórios que Laravel escreve:

```sh
docker compose -f backend/compose.yaml exec laravel.test chown -R sail:sail .env storage bootstrap/cache
docker compose -f backend/compose.yaml restart laravel.test
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate
```

Aguarde o servidor iniciar após o restart. Use `--user sail` nos comandos Artisan
para manter os arquivos gerados acessíveis ao processo web. No WSL/Linux, também
é possível usar `./vendor/bin/sail` dentro de [backend/](./backend/).

As migrations criam as tabelas iniciais do Laravel e a tabela de produtos.
Para popular o banco de desenvolvimento:

```sh
docker compose -f backend/compose.yaml exec --user sail laravel.test php scripts/init-env.php
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan db:seed
```

A carga cria 24 produtos fictícios, três categorias (Camisetas, Canecas e Ecobags),
preços variados e itens com e sem estoque. Imagens são nulas, sem downloads ou
dependência de serviços externos. Nomes estáveis evitam duplicação ao repetir
a carga; produtos editados localmente são preservados enquanto mantiverem o nome.

O usuário padrão é `dev@example.test`. Sua senha vem de `SEED_USER_PASSWORD` no
ambiente local, gerada pelo script e nunca impressa. Consulte esse valor somente
no seu arquivo privado. O seeder preserva usuários existentes e não redefine
suas senhas. Os seeders de demonstração aceitam apenas ambientes local/testing.
Entre em `/login` usando essas credenciais locais. A página `/conta` permite sair da sessão.

### 4. Acessar

- Interface: http://localhost:5173 — catálogo público de produtos.
- Saúde da API e MySQL: http://localhost:8081/api/health.
- Saúde básica do Laravel: http://localhost:8081/up.

`GET /api/health` executa `SELECT 1` e retorna HTTP 200:

```json
{"data":{"status":"ok","database":"ok"}}
```

Se a consulta falhar, retorna HTTP 503 com os estados `unavailable`, sem expor
mensagens internas da conexão.

## Variáveis de ambiente

Os exemplos versionados documentam a configuração sem segredos:
[backend/.env.example](./backend/.env.example) e
[frontend/.env.example](./frontend/.env.example).

| Variável | Finalidade / padrão |
|---|---|
| `APP_KEY` | Chave gerada pelo script de ambiente |
| `APP_URL` / `APP_PORT` | URL local / porta 8081 |
| `FRONTEND_PORT` | Porta publicada pelo Compose para o Vue, padrão 5173 |
| `APP_DEBUG` | `false`, para evitar exposição de detalhes internos |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` | `mysql` / `mysql` / `3306` |
| `DB_DATABASE` / `DB_USERNAME` | Banco e usuário `chico_prince` |
| `DB_PASSWORD` / `DB_ROOT_PASSWORD` | Senhas geradas localmente |
| `SEED_USER_EMAIL` | Email do usuário de demonstração, padrão dev@example.test |
| `SEED_USER_PASSWORD` | Senha local gerada pelo script; mínimo de 12 caracteres |
| `WWWUSER` / `WWWGROUP` | UID/GID do usuário Sail, padrão 1000 |
| `VITE_API_BASE_URL` | `/api`, prefixo usado pelo navegador |
| `API_PROXY_TARGET` | `http://laravel.test` no Compose; `http://127.0.0.1:8081` para Vite no host |

O frontend funciona sem copiar seu exemplo: os padrões atendem ao Compose.
Após inicializar o volume MySQL, mudar uma senha no ambiente não altera a senha
armazenada no banco; as duas precisam ser atualizadas de forma coordenada.

## Decisões e trade-offs

- Monorepo para manter API, interface e instruções de execução juntas.
- Sail usa o runtime oficial do Laravel; simplifica o setup, mas a imagem inclui
  ferramentas que aumentam o tamanho e a duração do primeiro build.
- Node/Vite roda em serviço separado, com dependências próprias.
- O proxy do Vite permite consumir a API pela mesma origem em desenvolvimento.
  Ele não substitui a configuração de entrega da aplicação em produção.
- MySQL não publica porta no host. Seu volume preserva os dados ao recriar containers.
- Polling no Vite facilita detectar mudanças em bind mounts, com maior uso de CPU.
- A API usa recursos nativos do Laravel. No Vue, Axios centraliza comunicação
  HTTP e erros; um composable compartilha a sessão sem adicionar uma biblioteca de store.

## Modelo de produtos

O [modelo Product](./backend/app/Models/Product.php) usa a tabela `products`:

| Campo | Armazenamento / regra |
|---|---|
| `id` | Identificador inteiro gerado pelo banco |
| `name` | Texto obrigatório, até 255 caracteres |
| `description` | Texto longo obrigatório |
| `image` | URL opcional, até 2048 caracteres; sem upload nesta etapa |
| `price` | `DECIMAL(10,2)`, lido como string com duas casas para preservar precisão |
| `category` | Texto obrigatório, até 100 caracteres, com índice para filtragem |
| `stock` | Inteiro sem sinal no MySQL, padrão zero |
| `created_at` / `updated_at` | Datas administradas pelo Eloquent |

Categoria como texto evita uma entidade de gerenciamento sem necessidade atual.
O campo de imagem armazena somente o endereço; não baixa arquivos externos.
FormRequests validam URL, preço não negativo e demais entradas. Listagem e detalhe
são públicos; criação, atualização e exclusão exigem sessão autenticada no backend.

## Operação do ambiente

Todos os comandos partem da raiz do repositório:

```sh
# Iniciar novamente; --build é necessário quando a imagem mudar
docker compose -f backend/compose.yaml up -d
# Parar mantendo containers e volumes
docker compose -f backend/compose.yaml stop
# Remover containers e rede, preservando os dados
docker compose -f backend/compose.yaml down
# Consultar logs recentes
docker compose -f backend/compose.yaml logs --tail=100
# Abrir shell no backend ou no frontend
docker compose -f backend/compose.yaml exec --user sail laravel.test bash
docker compose -f backend/compose.yaml exec frontend sh
# Aplicar migrations pendentes
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate
```

Para recriar as tabelas **apagando todos os dados do banco local**, use somente
quando esse descarte for intencional:

```sh
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate:fresh
```

Para recriar e popular o banco descartando os dados atuais, use
`php artisan migrate:fresh --seed` no lugar de `php artisan migrate:fresh`.
Não use `down -v` no fluxo normal: ele remove também os volumes persistentes.

## Contrato da API

O [contrato REST](./docs/API.md) descreve rotas, acesso, payloads, paginação,
parâmetros de filtros e códigos HTTP. ProductResource e o tratamento padronizado
de exceções estão implementados e testados, assim como as rotas de sessão e CRUD.
O fluxo de cookies/CSRF está descrito no contrato. Sanctum 4 autentica a sessão web;
não são usados tokens Bearer. A listagem combina busca, categoria, faixa de preço
e disponibilidade, com paginação de 1 a 100 itens e ordenação validada.

Operações de escrita registram ação, ID do produto e ID do usuário no canal
`products`, com rotação diária e retenção de 14 dias. Os arquivos locais
`backend/storage/logs/products-YYYY-MM-DD.log` não são versionados.
O registro é síncrono e operacional, sem garantia de auditoria transacional.

## Base da interface

Vue Router organiza `/` (catálogo), `/produtos/:id` (detalhe público), `/login`,
`/conta` (consulta da sessão e logout) e a página 404.
O layout compartilha a navegação; cada rota atualiza o título da página.
Axios centraliza base da API, cookies, CSRF, timeout e normalização dos erros.
O composable `useAuth` compartilha a sessão em memória, sem armazenar senhas ou
tokens no armazenamento do navegador, e oferece consulta, login e logout.
Uma sessão ausente é tratada como visitante; falhas de conexão permitem nova tentativa.

O catálogo usa cards com preço em reais, categoria, disponibilidade e alternativa
para imagens ausentes ou com falha. O formulário combina busca por nome, categoria
exata, preços, estoque, ordenação e quantidade de itens (1–100). A categoria é texto
livre, pois o domínio permite novas categorias e não possui endpoint de categorias.
Os filtros são aplicados ao enviar o formulário, sem consultas a cada tecla;
alterá-los reinicia a paginação. A URL preserva a consulta ao recarregar, compartilhar
ou navegar pelo histórico. Requisições anteriores são canceladas para evitar que
respostas atrasadas substituam a consulta atual. Há estados de carregamento, vazio
e erro com nova tentativa.

O detalhe mostra descrição, imagem, preço, categoria e estoque; produtos inexistentes
recebem uma mensagem específica. O retorno ao catálogo preserva a consulta na URL.
Imagem e fallback são compartilhados entre cards e detalhe.

O login prepara o CSRF e autentica por cookie de sessão; credenciais inválidas,
falha de conexão, expiração do CSRF e limite de tentativas recebem feedback.
Após entrar, a interface abre a conta; ao recarregar, consulta a sessão no servidor.
A senha é limpa ao concluir cada tentativa. Usuários autenticados que acessam
`/login` são encaminhados para `/conta`. Logout atualiza o estado compartilhado.

Usuários autenticados acessam `/produtos/novo` pelo cabeçalho e edição em
`/produtos/:id/editar` pelo detalhe. Uma única view deriva `isEdit` do ID da rota:
criação envia POST e edição carrega os dados e envia PATCH. O formulário apresenta
validações por campo e bloqueia envios duplicados enquanto salva. A imagem é uma
URL opcional, sem upload. Após criar ou editar com sucesso, a interface retorna
automaticamente ao catálogo, que consulta os dados atualizados. Falhas mantêm o formulário aberto.
No detalhe, excluir exige confirmação nativa do navegador; cancelar não envia DELETE.
A interface exibe sucesso ou erro, e a API exige sessão e CSRF nas três operações.

Em produção,
o servidor da SPA deverá redirecionar rotas do frontend para index.html, preservando
as rotas `/api`; o Vite já oferece esse fallback em desenvolvimento.

## Verificações

Veja o [guia de testes](./docs/TESTING.md) para cobertura e execução em MySQL isolado.

```sh
docker compose -f backend/compose.yaml ps
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate:status
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan test
docker compose -f backend/compose.yaml exec frontend npm run build
```

Verificado no ambiente de desenvolvimento:

- Build e inicialização dos três serviços pelo Compose.
- Migrations iniciais executadas no MySQL.
- Suíte backend padrão: 32 testes passaram, 224 assertions; o teste específico de
  estoque negativo no MySQL é ignorado quando o banco de teste é SQLite.
- Suíte completa também executada em MySQL exclusivo de testes: 33 testes passaram,
  226 assertions, sem casos ignorados. Inclui autenticação, CRUD, validação, erros,
  filtros, paginação, logs e restrições reais do banco.
- Reversão e reaplicação da migration de produtos verificadas no banco isolado.
- TypeScript e build Vite executados no container Node.
- HTTP 200 nas rotas de saúde e no proxy do Vite, com consulta real ao MySQL.
- Fluxo HTTP real via proxy e MySQL: sessão, CSRF, CRUD, validação e logout;
  produto temporário removido ao final. Os testes PHPUnit usam SQLite, onde o
  middleware desabilita CSRF; por isso essa proteção foi verificada por HTTP real.
- Navegador: estados de erro e sucesso da conexão, incluindo nova tentativa.
- Base SPA: rota direta de conta, navegação, página 404 e recuperação após falha
  de rede simulada na consulta de sessão. Sem suíte automatizada de frontend.
- Filtros combinados e navegação entre páginas verificados no MySQL real via proxy;
  links usam o host acessível ao navegador. Build frontend passou após o ajuste.
- Catálogo no navegador como visitante: filtros combinados, preços em ordem
  decrescente, paginação, restauração pelo histórico, reset da página ao filtrar,
  resultado vazio, validação 422 e recuperação após falha de rede simulada.
- Detalhe público, produto inexistente, retorno com filtros e recuperação após
  falha de rede; login real com usuário temporário, senha inválida, restauração
  da sessão ao recarregar, redirecionamento de autenticado e logout. O usuário
  temporário foi removido ao final.
- CRUD pela interface: visitante sem formulário de escrita; criação, erro 422 por
  campo, edição de preço/estoque, cancelamento e confirmação de exclusão. Produto
  e usuário temporários removidos ao final.

O procedimento foi executado também em uma cópia isolada dos arquivos versionados,
sem ambiente nem dependências locais copiados, com banco e volumes novos.
Foram verificados bootstrap, migrations, build, HTTP, reset do banco descartável e
persistência após `down` seguido de `up`. O cache de imagens Docker foi reutilizado;
isso não equivale a testar em outro computador ou sem cache de imagens.
Não foi adicionada suíte automatizada de frontend nesta etapa.

## Limitações e próximos passos

Ainda faltam a revisão de responsividade/acessibilidade, segurança e o polimento da
entrega. Mensagens detalhadas de validação do Laravel ainda usam o idioma padrão
em inglês. O formato de erros e o Resource de produtos estão preparados;
o endpoint de saúde mantém seu formato explícito de diagnóstico.

Importação externa, filas, monitoramento e Elasticsearch não estão implementados.
São opcionais e ficam subordinados à conclusão dos requisitos obrigatórios.
Nenhum serviço externo de produtos é necessário para executar a base atual.
