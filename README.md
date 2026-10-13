# Chico Prince

Aplicação de catálogo de produtos desenvolvida para um teste técnico Full Stack,
com API Laravel, banco MySQL e interface Vue 3 que consome a própria API.

## Estado do projeto

O catálogo público oferece busca, filtros combinados, ordenação, paginação e detalhes.
O painel exclusivo de administradores concentra criação, edição e exclusão de produtos, variações
de cor/tamanho com estoque próprio e banners por categoria.

O carrinho funciona em modal lateral, com persistência local e seleção obrigatória
de cor e tamanho no detalhe. O checkout demonstrativo oferece endereço,
consulta de CEP, resumo e cupom PRIMEIRACOMPRA (10% no primeiro pedido). A finalização autenticada cria o pedido concluído e baixa estoque, sem cobrança financeira ou frete real.

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

As migrations criam as tabelas do Laravel, produtos, variações e banners.
Para inicializar um banco vazio com dados fictícios, sem acesso externo:

```sh
docker compose -f backend/compose.yaml exec --user sail laravel.test php scripts/init-env.php
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --offline
```

A carga offline cria 24 produtos fictícios, três categorias (Camisetas, Canecas e Ecobags),
preços variados e itens com e sem estoque. Imagens são nulas, sem downloads ou
dependência de serviços externos. Nomes estáveis evitam duplicação ao repetir
db:seed diretamente; o comando catalog:bootstrap preserva integralmente um catálogo já preenchido.

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
são públicos; criação, atualização e exclusão exigem sessão de administrador no backend.

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
Após entrar, a interface retorna ao checkout quando ele originou o login; nos demais casos abre a conta. Ao recarregar, consulta a sessão no servidor.
A senha é limpa ao concluir cada tentativa. Usuários autenticados que acessam
`/login` são encaminhados para `/conta`. Logout atualiza o estado compartilhado.

Administradores acessam `/admin/products` pelo cabeçalho. O painel reúne
tabela com todos os campos e imagens, busca, filtros, ordenação, paginação e
visualização conjunta dos selecionados. A seleção fica restrita à página atual
e é limpa ao mudar os filtros ou a página. O backend exige o papel de administrador para gerenciar produtos e banners; clientes recebem 403 nessas operações.

Criar (`/admin/products/new`) e editar (`/admin/products/:id/edit`) usam uma única
view que deriva `isEdit` do ID da rota:
criação envia POST e edição carrega os dados e envia PATCH. O formulário apresenta
validações por campo e bloqueia envios duplicados enquanto salva. A imagem é uma
URL opcional, sem upload. Após criar ou editar com sucesso, a interface retorna
automaticamente à tabela, preservando os filtros e consultando dados atualizados.
Falhas mantêm o formulário aberto. As antigas rotas de formulário redirecionam ao painel.
Excluir um produto ou a seleção exige confirmação com nomes e quantidade;
cancelar não envia DELETE. A exclusão em lote usa chamadas individuais sequenciais,
informa falhas parciais e mantém somente os itens pendentes selecionados para nova tentativa.
Não há garantia de atomicidade do lote. Gerenciamento não aparece no detalhe público.
A interface exibe sucesso ou erro, e a API exige sessão e CSRF nas três operações.

Catálogo e painel compartilham consulta e paginação. Consultas anteriores são
canceladas e a lista permanece visível durante atualização. O bootstrap de CSRF
só é solicitado quando o cookie está ausente; validação no servidor permanece ativa.
O layout oferece atalho para o conteúdo, foco após navegação, labels e tabela com
rolagem horizontal acessível. Imagens externas usam apenas HTTP(S), carregamento
adiado, fallback e política sem envio de Referer.

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
- Painel: filtros/paginação, seleção da página, visualização conjunta, criação e edição
  com retorno à tabela, cancelamento e exclusão parcial simulada com repetição somente
  da falha. CSRF sem header rejeitado com 419 por HTTP real.

Veja a [revisão de segurança e desempenho](./docs/REVIEW.md) para escopo e limitações.

O procedimento foi executado também em uma cópia isolada dos arquivos versionados,
sem ambiente nem dependências locais copiados, com banco e volumes novos.
Foram verificados bootstrap, migrations, build, HTTP, reset do banco descartável e
persistência após `down` seguido de `up`. O cache de imagens Docker foi reutilizado;
isso não equivale a testar em outro computador ou sem cache de imagens.
Não foi adicionada suíte automatizada de frontend nesta etapa.

## Limitações e próximos passos

Ainda falta o polimento final da entrega.
Carrinho lateral e seleção obrigatória de cor/tamanho no detalhe estão implementados.
O checkout cria pedidos demonstrativos concluídos e valida preços, estoque e cupom no servidor. PRIMEIRACOMPRA concede 10% sobre os produtos apenas no primeiro pedido da conta. Não há pagamento real, cálculo de frete, vale-presente, histórico visual de pedidos ou cancelamento.
Mensagens detalhadas de validação do Laravel ainda usam o idioma padrão
em inglês. O formato de erros e o Resource de produtos estão preparados;
o endpoint de saúde mantém seu formato explícito de diagnóstico.

Coleta e carga inicial opcionais da Chico Rei estão disponíveis via Docker.
Sincronização de catálogos existentes, filas, monitoramento e Elasticsearch não estão implementados.
São opcionais e ficam subordinados à conclusão dos requisitos obrigatórios.
Nenhum serviço externo de produtos é necessário para executar a base atual.

## Carrinho e variações

O botão flutuante abre o carrinho lateral. Comprar aparece somente no detalhe,
após selecionar cor e tamanho. IDs de produto/variação e quantidades persistem no
navegador; preços e disponibilidade são consultados novamente na API. O carrinho
é compartilhado entre sessões nesse navegador, sem sincronização entre dispositivos.
Totais locais são informativos; a finalização recalcula valores e disponibilidade no servidor.

O painel permite cadastrar combinações de cor/tamanho e estoque. O estoque total
é calculado a partir delas, enquanto preço e imagem são compartilhados. Execute
as migrations pendentes ao atualizar a instalação. Produtos anteriores permanecem
intactos e precisam de variações cadastradas para habilitar Comprar.
A tela /checkout permite revisar itens e preencher endereço, com consulta ao
[ViaCEP](https://viacep.com.br/) e preenchimento manual quando o serviço falha.
## Busca e banners do catálogo

A busca inicial exibe somente o campo de nome e as ações Buscar/Busca avançada.
Filtros adicionais podem ser expandidos; categorias cadastradas aparecem como sugestões.
Selecionar uma categoria abre seu caminho no catálogo (por exemplo, /calcas);
texto, preço, ordem e paginação permanecem nos parâmetros da URL. Histórico,
recarregamento e retorno do detalhe preservam a consulta.

Em Gerenciar produtos, a seção de banners permite configurar imagem por URL,
texto, categoria de destino, ordem e ativação. O carrossel manual exibe até 20
banners ativos; cada clique aplica o filtro correspondente. Sem banners cadastrados,
a seção não aparece. Não há upload de imagens nem descontos automáticos.
Categorias derivam dos produtos: renomear/remover uma categoria pode mudar seus
links ou ocultar seus banners. Slugs em colisão recebem sufixo para distinguir destinos.

Atualização: aplicar migrations pendentes para criar a tabela de banners.
Verificação deste incremento: build e suíte MySQL isolada com 42 testes /
341 assertions aprovados, incluindo categorias, banners e permissões.
## Carga inicial opcional com produtos da Chico Rei

Depois das migrations e da configuração do ambiente, escolha UMA das cargas:
offline (acima), coleta online (abaixo) ou JSON de coleta anterior.
Não execute a carga offline antes da online se quiser inicializar com produtos reais.

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --limit=20

O comando roda o coletor Python dentro do Sail. Em catálogo vazio, usa os produtos
válidos encontrados; se não houver nenhum ou a coleta falhar, gera os 24 fictícios.
Uma amostra parcial válida é aceita. Os usuários de demonstração são criados em ambos os caminhos; os dois acessos públicos de teste têm suas credenciais restauradas pelo seeder. O usuário legado mantém sua senha existente. Escrita dos produtos é transacional.

Por padrão, se o catálogo já tem qualquer produto, o comando termina sem coleta nem alteração.
A substituição seletiva de fictícios exige --replace-demo (descrita abaixo). A escolha padrão acontece antes da
primeira carga; reiniciar o Docker também não executa coleta automaticamente.
As migrations criam estrutura; os seeders/comandos de carga criam os dados.

A pasta [scraper/chicorei](./scraper/chicorei/) é montada em /opt/chicorei no container.
Em instalações existentes, recrie o serviço para aplicar esse mount:

    docker compose -f backend/compose.yaml up -d --no-deps laravel.test

Para usar a amostra local já coletada, substitua PASTA_DA_EXECUCAO pelo diretório real:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --file=/opt/chicorei/data/PASTA_DA_EXECUCAO/products.json

O padrão é 20; --limit aceita de 1 a 45. Quarenta e cinco produtos exigem cerca de
4 minutos com intervalo de 5 segundos, podendo demorar mais ou resultar em menos
dados. Timeout total do processo: 2200 segundos. Erros de acesso, bloqueios e
mudanças no HTML podem interromper a coleta; não há promessa de catálogo completo.

As imagens permanecem URLs externas e podem ficar indisponíveis. Não são baixadas
nem validadas por download durante a carga. Estoques quantitativos e variações não foram extraídos da origem. O seeder de suporte cria dados demonstrativos: COR DA IMAGEM, tamanhos P/M/G para Camiseta/Camisa (ÚNICO para outros tipos), 5 unidades por tamanho. A interface identifica esse caráter demonstrativo. O seeder fictício também não
configura variações automaticamente.

A carga é exclusiva de local/testing. Não sincroniza produtos existentes nem
substitui um sistema de estoque. Os dados coletados são locais e ignorados pelo Git.
Testes: 49 testes / 378 assertions no MySQL isolado e quatro testes Python;
coleta real de um produto verificada dentro do Docker.
### Substituir somente fictícios intactos

Primeiro simule usando o JSON coletado:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --limit=45 --file=/opt/chicorei/data/PASTA_DA_EXECUCAO/products.json --replace-demo --dry-run

Confira os IDs candidatos; depois repita sem --dry-run.
Somente produtos idênticos ao seeder, sem variações, sem origem de importação e
sem registro de atualização são candidatos. Produtos editados ou próprios permanecem.

Antes das exclusões, o comando salva uma cópia dos produtos e variações em
storage/app/private/catalog-backups, fora do Git. Se o backup falhar, a troca não ocorre.
Criação e exclusão fazem parte da mesma transação. Uma amostra inválida/vazia
preserva o catálogo existente. O modo padrão de inicialização vazia mantém o fallback.

A migration de suporte adiciona import_key, source_url e demo_variants.
import_key identifica a URL de origem para evitar duplicação nas reexecuções.
Produtos importados existentes não são atualizados automaticamente.
ProductDemoVariantsSeeder preenche apenas importados sem variações e ainda não
complementados; reexecutá-lo não repõe estoque nem recria variações removidas
de produtos já complementados. Os valores são fictícios, não informação da Chico Rei.

Para executar o complemento separadamente:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan db:seed --class=ProductDemoVariantsSeeder
## Checkout demonstrativo e acessos de avaliação

Ao continuar para o checkout, visitantes são encaminhados ao login e retornam
ao checkout após autenticar. Finalizar compra persiste um pedido completed,
baixa o estoque da combinação e remove as unidades compradas do carrinho.
Preços, estoque e desconto são conferidos em transação pelo servidor.
Uma chave de tentativa impede duplicar o pedido ao reenviar o mesmo conteúdo.
Frete sem cobrança e ausência de pagamento real são identificados na interface.

Em ambiente local/testing, o login apresenta os acessos públicos de demonstração:

| Papel | E-mail | Senha |
|---|---|---|
| Administrador | admin@chicoprince.com | 123456 |
| Cliente | teste@chicoprince.com | 654321 |

Para atualizar uma instalação existente:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate
    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan db:seed --class=DemoAccountsSeeder

Esse seeder cria ou restaura apenas os dois acessos de avaliação. Usuários existentes
permanecem clientes; não há promoção geral. Credenciais e endpoint de demonstração
são restritos a local/testing e não devem ser usados em produção.

## Categorias da coleta e seleção de variações

A importação preserva category_path como uma lista de categorias. Sugestões, banners
e filtros aceitam qualquer item dessa lista, além da categoria principal.
O campo continua identificado como Categoria na interface. Para complementar uma
carga anterior sem substituir produtos, preços, estoques ou edições:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:categories /opt/chicorei/data/PASTA_DA_EXECUCAO/products.json

O comando só preenche caminhos ausentes em produtos com a mesma origem importada.
Cor e tamanho são selecionados por botões circulares; tamanhos sem estoque para
a cor selecionada permanecem visíveis, opacos e desabilitados.
A opção Cor da imagem usa três faixas diagonais e identificação no hover.

Verificação mais recente: 56 testes de backend / 444 assertions em MySQL isolado
e build TypeScript/Vite aprovados. Compra, login e permissões também foram
verificados no navegador. A instalação limpa não foi repetida após estes incrementos.