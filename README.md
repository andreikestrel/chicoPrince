# Chico Prince

Aplicação de catálogo de produtos desenvolvida para um teste técnico Full Stack,
com API Laravel, banco MySQL e interface Vue 3 que consome a própria API.

## Estado do projeto

A base de desenvolvimento está funcional: Laravel e MySQL executam em containers,
as migrations iniciais estão aplicadas e o Vue consulta a saúde da API e do banco.
O catálogo, os dados de exemplo, a autenticação e o CRUD ainda estão em desenvolvimento.

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
e senhas aleatórias. Se ele já existe, seus valores são preservados.
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

As migrations atuais criam as tabelas iniciais do Laravel. Migrations e seeders de
produtos ainda não foram implementados; não há catálogo de exemplo nem credencial
de login disponível nesta etapa.

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
| `APP_DEBUG` | `false`, para evitar exposição de detalhes internos |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` | `mysql` / `mysql` / `3306` |
| `DB_DATABASE` / `DB_USERNAME` | Banco e usuário `chico_prince` |
| `DB_PASSWORD` / `DB_ROOT_PASSWORD` | Senhas geradas localmente |
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
- Suíte backend: 4 testes e 7 assertions, usando SQLite isolado nos testes.
- TypeScript e build Vite executados no container Node.
- HTTP 200 nas rotas de saúde e no proxy do Vite, com consulta real ao MySQL.
- Navegador: estados de erro e sucesso da conexão, incluindo nova tentativa.

A instalação integral em uma máquina limpa ainda precisa ser validada.
Não foi adicionada suíte automatizada de frontend nesta etapa.

## Limitações e próximos passos

Ainda faltam os requisitos de domínio: produtos e seeders, autenticação,
FormRequests, CRUD, filtros combináveis, paginação, ordenação, logs e as respectivas
interfaces. A padronização geral dos erros e respostas será definida junto ao
contrato da API; o endpoint de saúde já possui formato explícito.

Importação externa, filas, monitoramento e Elasticsearch não estão implementados.
São opcionais e ficam subordinados à conclusão dos requisitos obrigatórios.
Nenhum serviço externo de produtos é necessário para executar a base atual.
