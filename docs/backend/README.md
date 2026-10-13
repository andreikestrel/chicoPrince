# Backend — API e dados

[Projeto](../../README.md) · [Contrato REST](../API.md) · [Testes](../TESTING.md)

Laravel 12/PHP 8.4, usando rotas, middleware, FormRequests, controllers, Eloquent e
Resources. Sem camada genérica de repositórios ou DTOs.

## Organização

| Diretório | Responsabilidade |
|---|---|
| [Rotas](../../backend/routes/api.php) | Saúde, sessão, catálogo, gerenciamento e pedidos |
| [Controllers](../../backend/app/Http/Controllers/) | Operações e transações |
| [Requests](../../backend/app/Http/Requests/) | Validação |
| [Resources](../../backend/app/Http/Resources/) | Contratos de saída |
| [Models](../../backend/app/Models/) | Entidades e relações |
| [Migrations](../../backend/database/migrations/) | Estrutura e restrições |
| [Seeders](../../backend/database/seeders/) | Dados fictícios, contas e complemento da coleta |
| [Comandos](../../backend/app/Console/Commands/) | Inicialização e complemento de categorias |
| [Testes](../../backend/tests/) | Casos unitários e de integração |

## Dados

- Product: nome, descrição, URL de imagem, preço DECIMAL(10,2), categoria principal,
  estoque agregado e timestamps. Importação acrescenta origem, chave única,
  marcador de variantes demonstrativas e category_path JSON.
- ProductVariant: produto, combinação única cor/tamanho e estoque próprio.
  O CRUD preserva IDs de combinações existentes.
- User: sessão e papel is_admin. O papel não aceita mass assignment do cliente.
- Banner: título, imagem, categoria de destino, posição e ativo/inativo.
- Order: usuário, chave única de tentativa, hash do conteúdo, status completed,
  endereço e itens em snapshots JSON, valores em centavos e timestamps.

Categorias disponíveis unem o campo principal e itens do category_path. Slugs tratam
colisões e nomes reservados. Filtros buscam o campo principal ou presença no JSON.
Não existe gerenciamento separado de taxonomia.

## Autenticação e autorização

Leitura é pública. Login/logout usam sessão web com Sanctum e CSRF. A SPA prepara
o cookie CSRF e envia X-XSRF-TOKEN; cookie de sessão é HttpOnly.
Tokens Bearer não são emitidos nem aceitos.

Login regenera a sessão e logout a invalida. Limite: cinco tentativas/minuto/IP.
RequireAdmin protege produtos e banners. Cliente autenticado pode criar pedidos.
Visitante recebe 401; cliente tentando administrar recebe 403.

GET /api/demo-accounts e DemoAccountsSeeder são exclusivos de local/testing.
O seeder cria/restaura os dois acessos públicos; não promove usuários existentes.
DemoUserSeeder preserva o usuário legado com senha privada de no mínimo 12 caracteres.

## Produtos, filtros e logs

Listagem combina nome literal, categoria, preço e disponibilidade; ordenação por
allowlist, desempate por ID e per_page de 1–100. PUT substitui campos editáveis;
PATCH aceita atualização parcial. Preço sai como string decimal.
Erros seguem envelope com código, mensagem e detalhes, conforme [contrato](../API.md).

Salvar variações normaliza cor/tamanho, rejeita repetições e recalcula estoque total.
Com variações, estoque agregado não é recebido arbitrariamente do formulário.
Logs de criação/edição/exclusão contêm ação, product_id e user_id. Canal products
usa rotação diária e retenção de 14 dias; não é auditoria transacional.

## Finalização demonstrativa

1. Valida endereço, cupom, UUID e itens com quantidade/preço esperado.
2. Em transação, bloqueia usuário, verifica tentativa e bloqueia produtos/variações.
3. Confere preços com o banco e valida o estoque de todas as combinações.
4. PRIMEIRACOMPRA dá 10% no primeiro pedido, com arredondamento em centavos.
5. Baixa estoque, atualiza agregados e grava o pedido completed.

Reenvio idêntico retorna o mesmo pedido em 200; criação retorna 201. Mesma chave com
outros dados retorna 422. Falhas de validação não criam pedido nem baixam estoque.
Frete é zero; não há instituição financeira envolvida. Endereço persiste no banco
e não é devolvido na resposta de criação.

## Ambiente e operação

Instalação no [README](../../README.md). O [Compose](../../backend/compose.yaml)
inicia Sail, Node/Vite e MySQL. Banco somente na rede interna e em volume nomeado;
HTTP publicado em loopback. Healthcheck consulta com usuário da aplicação.

| Variável | Uso |
|---|---|
| APP_KEY | Gerada pelo script de ambiente |
| APP_ENV / APP_DEBUG | local / false |
| APP_URL / APP_PORT | URL do backend / 8081 |
| FRONTEND_PORT | Porta externa Vue, 5173 |
| DB_HOST / DB_PORT | mysql / 3306 internos |
| DB_DATABASE / DB_USERNAME | chico_prince |
| DB_PASSWORD / DB_ROOT_PASSWORD | Geradas localmente |
| WWWUSER / WWWGROUP | UID/GID Sail, 1000 |
| SEED_USER_EMAIL / SEED_USER_PASSWORD | Usuário legado, senha aleatória |
| VITE_API_BASE_URL | /api |
| API_PROXY_TARGET | http://laravel.test no frontend Docker |

Atualizar estrutura e restaurar acessos de avaliação:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate
    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan db:seed --class=DemoAccountsSeeder

Consultar migrations e abrir shell:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate:status
    docker compose -f backend/compose.yaml exec --user sail laravel.test bash

## Diagnóstico

- Porta ocupada: ajuste APP_PORT/FRONTEND_PORT e recrie serviços. Segunda instalação
  precisa também de outro nome de projeto Compose (-p).
- Permissão em cache/log: siga chown da instalação e execute Artisan como sail.
- MySQL unhealthy: consulte logs. Mudar senha no ambiente não muda a persistida no volume.
- Frontend iniciando: aguarde npm ci nos logs.
- Contas ausentes em banco antigo: execute DemoAccountsSeeder.
- Produto não comprável: cadastre variações com estoque no painel.

## Segurança e limitações

Queries parametrizadas, protocolos de imagem validados, Resource explícito e
templates escapados. Segredos, logs e dados coletados não são versionados.
CSRF precisa de teste HTTP real: PHPUnit o desabilita.

Sail simplifica reprodução, mas aumenta o primeiro build. Bind mounts lentos podem
afetar leitura dos arquivos PHP. Busca substring e categorias calculadas em memória
atendem à amostra pequena; bases maiores exigem medição e possível normalização.
Não há ACL por produto, auditoria transacional ou ensaio de carga concorrente.
Produção exige servidor apropriado, HTTPS, cookies seguros e entrega SPA/API configurada.