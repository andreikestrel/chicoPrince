# Chico Prince

Projeto de teste técnico Full Stack: catálogo de produtos com API Laravel,
MySQL e aplicação Vue 3 consumindo a própria API.

## Estado atual

TASKS 0.3 e 0.4 em desenvolvimento. O backend e a base Vue estão criados.
A primeira construção do Sail foi interrompida por falta de espaço no disco C:.
O armazenamento do Docker será transferido para G: antes de retomar os containers.

O frontend compila e abre no navegador, mas a integração real com Laravel/MySQL
ainda não passou. CRUD, autenticação e catálogo serão implementados depois deste lote.

## Estrutura

- [backend/](./backend/): Laravel 12, endpoint de saúde, migrations iniciais e Sail.
- [frontend/](./frontend/): Vue 3, TypeScript, Vite e verificação de conexão.
- [backend/compose.yaml](./backend/compose.yaml): único Compose do monorepo.
- [backend/scripts/init-env.php](./backend/scripts/init-env.php): cria configuração
  local com valores aleatórios, sem sobrescrever ambiente existente.

Os marcadores `.gitkeep` originais foram preservados.

## Ambiente e armazenamento

- Docker Desktop 4.94.0, Engine 29.8.2 e Compose v5.5.1 foram validados na TASK 0.2,
  com Ubuntu integrado ao WSL2.
- Código, dependências do projeto e cache npm desta etapa ficam em G:.
- Destino solicitado para os dados do Docker: `G:\DockerData`, fora do repositório.
  A transferência ainda está pendente; não reiniciar builds enquanto o disco não
  estiver transferido ou houver espaço suficiente no local atual.
- O disco C: chegou a aproximadamente 15 MB livres durante os downloads/builds.
  O encerramento normal do Docker excedeu o prazo; nenhuma limpeza destrutiva
  de imagens ou volumes foi executada.

No PowerShell, se Docker não for reconhecido após a instalação, atualizar o PATH
da sessão ou fechar todas as janelas do VS Code e reabrir pelo menu Iniciar:

```powershell
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('Path', 'User')
docker compose version
```

## Arquitetura local

| Serviço | Runtime | Acesso no host | Persistência |
|---|---|---|---|
| `laravel.test` | Runtime oficial Sail / PHP 8.4 | `127.0.0.1:8081` → 80 | Bind mount do backend |
| `mysql` | MySQL 8.4 | Sem porta publicada | Volume `sail-mysql` em `/var/lib/mysql` |
| `frontend` | Node 24 / Vite | `127.0.0.1:5173` → 5173 | Bind mount do frontend e volume para `node_modules` |

A porta 8080 estava ocupada; adotamos 8081. As publicações ficam limitadas ao
loopback. Serviços compartilham a rede `sail`; Laravel acessa `mysql:3306`.
O healthcheck do MySQL executa uma consulta com o usuário da aplicação antes de
liberar a inicialização do backend.

O navegador solicita `/api/health` ao Vite. Dentro do Compose, o proxy encaminha
a chamada para `http://laravel.test`. Rodando Vite no host, o destino padrão é
`http://127.0.0.1:8081`. Isso dispensa CORS para este fluxo de desenvolvimento.
O proxy do Vite não é uma configuração de produção.

- **Imagem:** pacote do runtime usado para criar containers.
- **Container:** instância da imagem, que pode ser recriada.
- **Bind mount:** compartilha os arquivos locais de código com o container.
- **Volume nomeado:** mantém dados separados do ciclo de vida do container.
- **Porta publicada:** permite acesso pelo host; os serviços entre si usam nomes
  e portas da rede Docker.

MySQL mantém seus dados ao parar/recriar containers. `docker compose down -v`
remove volumes e dados; não usar no fluxo normal. Node/Vite roda separado do PHP.
O polling do Vite facilita observar mudanças no disco Windows, com maior uso de CPU.
Nenhuma pasta do projeto foi movida para o filesystem Linux.

## Preparação e execução

Os comandos abaixo devem ser executados na raiz do repositório, com Docker Desktop
em modo Linux e integração Ubuntu habilitada. A sequência completa em instalação
limpa será validada na TASK 0.5; a execução do Compose permanece bloqueada pelo disco.

Instalar dependências PHP e criar o ambiente local via container de bootstrap:

```powershell
docker run --rm --mount "type=bind,source=${PWD}/backend,target=/var/www/html" -w /var/www/html laravelsail/php84-composer:latest sh -lc 'php scripts/init-env.php && composer install --no-interaction'
docker compose -f backend/compose.yaml config --quiet
docker compose -f backend/compose.yaml up -d --build
docker compose -f backend/compose.yaml exec laravel.test php artisan migrate
```

O script gera `APP_KEY`, senha do usuário MySQL e senha administrativa apenas no
arquivo local ignorado `.env`. O exemplo permanece sem esses valores.
Se o ambiente já existe, o script o preserva. Não substituir as senhas depois de
inicializar o volume do banco sem planejar a atualização das credenciais no MySQL.

O frontend usa `npm ci` no início do serviço, respeitando seu arquivo de lock.
Seu exemplo de ambiente é opcional: os padrões já funcionam no Compose.
PHP/Composer e Node não precisam estar instalados no host para esse fluxo.

Acessos esperados após inicialização:

- Frontend: http://localhost:5173
- Saúde da API e banco: http://localhost:8081/api/health
- Saúde básica do Laravel: http://localhost:8081/up

O endpoint `/api/health` retorna HTTP 200 e
`{"data":{"status":"ok","database":"ok"}}` quando consegue executar `SELECT 1`.
Se o banco falha, retorna 503 sem divulgar mensagens internas da conexão.

No Ubuntu/WSL, dentro de [backend/](./backend/), também é possível usar
`./vendor/bin/sail` para os comandos do mesmo Compose.

## Verificações executadas neste lote

| Verificação | Resultado |
|---|---|
| Composer / Laravel | Dependências instaladas: Laravel 12.69.3 e Sail 1.68.0; lock gerado |
| Pint nos cinco arquivos PHP implementados | PASSOU |
| PHPUnit no container de bootstrap | PASSOU: 4 testes, 7 assertions; SQLite de teste, sem validar o MySQL real |
| Compose somente backend/MySQL | `config --quiet` passou antes de adicionar frontend |
| Build Sail e início do MySQL | INTERROMPIDOS por falta de espaço; migrations não executadas |
| `npm run build` | PASSOU: checagem TypeScript e build Vite 8.3.4 |
| Navegador | Vue carregou e mostrou estado de erro para API indisponível (502 do proxy) |
| Vue → Laravel → MySQL | PENDENTE |
| Compose com frontend | PENDENTE de validação após recuperar Docker |

O build do frontend foi executado com o Node 22.14.0 já instalado no G:, usando
cache em `frontend/node_modules/.cache`. A execução prevista no Compose usa Node 24.
Vue instalado: 3.5.43. Testes automatizados de frontend não foram adicionados.

Comandos de validação quando os containers estiverem disponíveis:

```powershell
docker compose -f backend/compose.yaml ps
docker compose -f backend/compose.yaml exec laravel.test php artisan migrate:status
docker compose -f backend/compose.yaml exec laravel.test php artisan test
docker compose -f backend/compose.yaml exec frontend npm run build
Invoke-RestMethod http://localhost:8081/api/health
Invoke-RestMethod http://localhost:5173/api/health
```

## Segurança e próximos passos

Ambientes locais, credenciais, dependências e builds ficam fora do Git. Locks devem
ser versionados. Este ambiente serve ao desenvolvimento local. O README público
fica na raiz; documentação de colaboração existente foi preservada.

Neste lote falta concluir armazenamento no G:, construir/subir os containers,
executar migrations e verificar a conexão real no navegador. As tarefas seguintes
dependem de aprovação humana.

Referências: [Laravel Sail](https://laravel.com/docs/12.x/sail),
[Docker com WSL2](https://docs.docker.com/desktop/features/wsl/),
[rede do Compose](https://docs.docker.com/compose/how-tos/networking/),
[volumes](https://docs.docker.com/engine/storage/volumes/) e
[Vite](https://vite.dev/guide/).
