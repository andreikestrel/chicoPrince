# Chico Prince

Aplicação de catálogo de produtos desenvolvida para um teste técnico Full Stack,
com API Laravel, banco MySQL e interface Vue 3 que consome a própria API.

## Estado do projeto

A base de desenvolvimento está funcional: Laravel e MySQL executam em containers,
as migrations iniciais estão aplicadas e o Vue consulta a saúde da API e do banco.
O modelo e os dados de exemplo estão disponíveis. O contrato REST está documentado;
a autenticação por sessão e o CRUD da API estão disponíveis. As telas do catálogo
e de login ainda estão em desenvolvimento.

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
O login já está disponível por API; a tela será implementada na etapa de frontend.

### 4. Acessar

- Interface: http://localhost:5173 — deve mostrar “Tudo conectado”.
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
- A base usa recursos nativos do Laravel e `fetch` no Vue, sem camadas extras para
  a consulta simples de saúde.

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
não são usados tokens Bearer. A listagem tem paginação fixa de 15 itens nesta etapa.

## Verificações

```sh
docker compose -f backend/compose.yaml ps
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate:status
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan test
docker compose -f backend/compose.yaml exec frontend npm run build
```

Verificado no ambiente de desenvolvimento:

- Build e inicialização dos três serviços pelo Compose.
- Migrations iniciais executadas no MySQL.
- Suíte backend padrão: 23 testes passaram, 140 assertions; o teste específico de
  estoque negativo no MySQL é ignorado quando o banco de teste é SQLite.
- Testes de produtos e seeders executados também em MySQL isolado: 5 passaram,
  26 assertions, incluindo precisão do preço, estoque negativo e repetição da carga.
- Reversão e reaplicação da migration de produtos verificadas no banco isolado.
- TypeScript e build Vite executados no container Node.
- HTTP 200 nas rotas de saúde e no proxy do Vite, com consulta real ao MySQL.
- Fluxo HTTP real via proxy e MySQL: sessão, CSRF, CRUD, validação e logout;
  produto temporário removido ao final. Os testes PHPUnit usam SQLite, onde o
  middleware desabilita CSRF; por isso essa proteção foi verificada por HTTP real.
- Navegador: estados de erro e sucesso da conexão, incluindo nova tentativa.

O procedimento foi executado também em uma cópia isolada dos arquivos versionados,
sem ambiente nem dependências locais copiados, com banco e volumes novos.
Foram verificados bootstrap, migrations, build, HTTP, reset do banco descartável e
persistência após `down` seguido de `up`. O cache de imagens Docker foi reutilizado;
isso não equivale a testar em outro computador ou sem cache de imagens.
Não foi adicionada suíte automatizada de frontend nesta etapa.

## Limitações e próximos passos

Ainda faltam filtros combináveis, paginação configurável, ordenação, logs e as respectivas
interfaces. O formato de erros e o Resource de produtos estão preparados;
o endpoint de saúde mantém seu formato explícito de diagnóstico.

Importação externa, filas, monitoramento e Elasticsearch não estão implementados.
São opcionais e ficam subordinados à conclusão dos requisitos obrigatórios.
Nenhum serviço externo de produtos é necessário para executar a base atual.
