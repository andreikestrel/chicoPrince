# Contrato REST

Contrato da API de produtos. Autenticação por sessão, CRUD, ProductResource e
tratamento de exceções, filtros, paginação configurável, ordenação e logs estão implementados.

## Rotas e códigos de sucesso

| Método e rota | Acesso | Resposta |
|---|---|---|
| GET /api/products | Público | 200, coleção paginada |
| GET /api/products/{id} | Público | 200, produto |
| POST /api/products | Administrador | 201, produto criado |
| PUT /api/products/{id} | Administrador | 200, substituição dos campos editáveis |
| PATCH /api/products/{id} | Administrador | 200, atualização parcial |
| DELETE /api/products/{id} | Administrador | 204, sem corpo |

A autorização de escrita é aplicada no backend via `auth:sanctum`. O middleware RequireAdmin também exige is_admin=true para produtos e banners; clientes recebem 403.

## Sessão e CSRF

| Método e rota | Resultado |
|---|---|
| GET /api/csrf-cookie | 204; inicia sessão e fornece cookie XSRF-TOKEN |
| POST /api/login | 200 com data contendo id, name, email, is_admin; email e password obrigatórios |
| GET /api/user | 200 com dados do usuário; 401 sem sessão |
| POST /api/logout | 204; invalida a sessão e renova o token CSRF |

A SPA usa cookies de mesma origem por meio do proxy `/api` do Vite.
As rotas de produtos e sessão usam o middleware `web` explicitamente: sessão,
criptografia de cookies e CSRF. O guard Sanctum autentica a sessão web; tokens
Bearer não são aceitos nem emitidos. Não é necessário armazenar tokens no localStorage.

Antes do login, faça GET /api/csrf-cookie com `credentials: 'include'`. Nas requisições
POST/PUT/PATCH/DELETE, envie o valor decodificado do cookie XSRF-TOKEN no header
X-XSRF-TOKEN, junto dos cookies e `Accept: application/json`. A sessão é HttpOnly;
somente o cookie de CSRF deve ser lido pelo JavaScript. Repita a preparação após
expiração da sessão. Cookie de sessão é renovado no login e invalidado no logout.

Credenciais incorretas retornam 401 genérico. O login permite cinco tentativas
por minuto por IP, incluindo tentativas válidas; excesso retorna 429 com Retry-After.
Sem CSRF válido, uma escrita retorna 419 antes da verificação de autenticação;
com CSRF válido, visitantes recebem 401. O endpoint de saúde dispensa sessão.

## Produto

```json
{
  "data": {
    "id": 1,
    "name": "Camiseta Horizonte",
    "description": "Produto fictício de demonstração.",
    "image": null,
    "price": "59.90",
    "category": "Camisetas",
    "stock": 10,
    "created_at": "2026-10-13T00:00:00.000000Z",
    "updated_at": "2026-10-13T00:00:00.000000Z"
  }
}
```

Preço monetário usa string decimal com duas casas; datas usam ISO 8601 em UTC.
Imagem é URL HTTP/HTTPS ou null. ID e timestamps são geridos pelo servidor.
Na criação: name, description, price e category obrigatórios; stock padrão 0;
image padrão null. PUT exige todos os campos editáveis; PATCH aceita um subconjunto.
FormRequests validam limites, URL, preço entre 0 e 99999999.99 com até duas casas
e estoque inteiro entre 0 e 4294967295.

## Listagem e paginação

A listagem usa 15 itens por página por padrão, ordenados por id ascendente.
Todos os parâmetros abaixo podem ser combinados por AND:

| Parâmetro | Regra |
|---|---|
| search | Busca parcial por nome, até 255 caracteres |
| category | Categoria exata, até 100 caracteres |
| min_price / max_price | Limites inclusivos; mínimo não pode superar máximo |
| in_stock | 1 filtra estoque > 0; 0 ou ausente não restringe disponibilidade |
| page | Inteiro >= 1; padrão 1 |
| per_page | Inteiro de 1 a 100; padrão 15 |
| sort | Allowlist: id, name, price, category, created_at, updated_at; padrão id |
| direction | asc ou desc; padrão asc |

Ordenação usa desempate por id na mesma direção para paginação previsível.
Parâmetros inválidos recebem 422. Os links preservam os filtros validados.
Busca trata `%`, `_` e `!` como caracteres literais; sensibilidade a maiúsculas
e acentos segue a collation do banco. Preços têm no máximo duas casas decimais.
Filtros textuais vazios são ignorados; page/per_page/sort/direction vazios são inválidos.

Exemplo: `/api/products?search=Canecas&category=Canecas&min_price=50&max_price=100&in_stock=1&per_page=2&sort=price&direction=desc`.

Coleções usam o formato nativo de Resources paginados do Laravel: `data` é uma
lista de produtos, `links` contém first/last/prev/next e `meta` inclui
current_page, from, to, last_page, per_page, total, path e links de navegação.
Lista vazia retorna 200 com data vazia e total zero.

Exemplo resumido de lista vazia (o Laravel também inclui links de navegação em meta):

```json
{
  "data": [],
  "links": {
    "first": "http://localhost:8081/api/products?page=1",
    "last": "http://localhost:8081/api/products?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": null,
    "to": null,
    "last_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

## Erros

Exceções nas rotas `/api/*` retornam JSON mesmo sem o header Accept.

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Os dados enviados são inválidos.",
    "details": {"name": ["O nome é obrigatório."]}
  }
}
```

| HTTP | error.code | Uso |
|---|---|---|
| 400 | bad_request | Requisição malformada |
| 401 | unauthenticated | Sessão ausente ou inválida |
| 403 | forbidden | Operação sem permissão |
| 404 | not_found | Rota ou produto inexistente |
| 405 | method_not_allowed | Método incompatível; preserva Allow |
| 419 | session_expired | Falha de sessão/CSRF |
| 422 | validation_failed | Campos ou parâmetros inválidos |
| 429 | too_many_requests | Limite de requisições; preserva Retry-After |
| 5xx | server_error | Falha interna; mensagem genérica |

`details` é objeto vazio salvo nos erros de validação, que contêm listas por campo.
O cliente deve usar `code` para identificar o erro; mensagens são destinadas ao
usuário. Stack traces, SQL e dados de conexão não são enviados ao cliente,
inclusive com debug habilitado. O registro interno de exceções do Laravel é mantido.

O diagnóstico existente `/api/health` mantém seu contrato próprio: 200 com
`data.status` e `data.database` iguais a `ok`, ou 503 com ambos `unavailable`.

## Logs de operações

Após cada criação, atualização ou exclusão bem-sucedida via API, o canal `products`
registra `product.created`, `product.updated` ou `product.deleted`, com `product_id`
e `user_id`. O logger acrescenta timestamp e nível. Não registra payloads,
descrições, credenciais, cookies ou tokens. Tentativas rejeitadas não geram eventos
de sucesso. Uma atualização válida sem mudança de valores ainda registra a operação.

Arquivos em `backend/storage/logs/products-YYYY-MM-DD.log`, rotação diária e retenção
de 14 dias. Escrita síncrona, sem filas. Esses logs são operacionais, não uma trilha
de auditoria transacional: não há atomicidade entre persistência no banco e arquivo.
Comandos de seed e alterações diretas no banco não passam por esse registro da API.

## Estado da implementação

- Disponível: saúde, sessão, CRUD, FormRequests, Resource e padronização de exceções.
- Disponível: filtros, ordenação/per_page configuráveis e logs de operações.
- Testes do contrato usam rotas temporárias exclusivas do processo PHPUnit;
  essas rotas não existem na aplicação em execução.

## Variações de produto
GET /api/products/{id}, POST e PATCH/PUT retornam também variants:
lista de {id, color, size, stock}. A listagem paginada mantém o formato compacto.

POST/PATCH/PUT aceitam variants como lista de até 100 objetos {color, size, stock}.
Cor (até 60 caracteres) e tamanho (até 30) são obrigatórios, normalizados para
maiúsculas e sem espaços nas pontas. Estoque por combinação: inteiro 0..1000000.
Combinações repetidas são rejeitadas com 422. Enviar a lista substitui o conjunto:
combinações mantidas preservam IDs; omitidas são removidas; lista vazia remove todas.
Omitir variants preserva o conjunto. Cadastro e variações são gravados em transação.
Quando existem variações, stock é a soma calculada; escrita direta desse agregado
é ignorada. Produtos antigos sem variações mantêm seu estoque até serem configurados.
Preço e imagem pertencem ao produto base; variações não têm preço independente.
## Categorias e banners

- GET /api/categories: público, data com pares {name, slug} das categorias distintas
  existentes nos produtos. Slugs sem acentos; colisões e rotas reservadas recebem
  sufixo determinístico. Categorias continuam sendo texto, não uma entidade cadastrada à parte.
- GET /api/products?category_slug=calcas: filtra por slug; combina com pesquisa,
  preços, estoque, ordem e paginação. Slug desconhecido: 404.
  category e category_slug juntos: 422.
- GET /api/banners: público, até 20 ativos com categoria existente, por position/id.
- GET /api/admin/banners: administrador, paginação de 20 itens, incluindo inativos.
- POST /api/admin/banners: administrador + CSRF, 201.
- PUT /api/admin/banners/{id}: administrador + CSRF, 200.
- DELETE /api/admin/banners/{id}: administrador + CSRF, 204.

POST/PUT recebem title (1–120 caracteres), image (URL HTTP(S), até 2048),
category (categoria já existente), position (inteiro 0–9999) e active (booleano).
Resposta em data: id e os mesmos campos. Categoria normalizada para o nome
cadastrado quando digitada com caixa diferente. Validação 422 e demais erros
seguem o envelope existente. Banners não criam promoções nem alteram preços.
## Pedidos demonstrativos

POST /api/orders exige sessão e CSRF. Corpo: request_key (UUID), coupon
(null ou PRIMEIRACOMPRA), items (1–100 combinações distintas, com variant_id,
quantity de 1–99 e unit_price como string decimal de duas casas) e address.
Endereço: recipient, cep, street, number, complement opcional, district, city,
state (UF com duas letras maiúsculas). Campos extras de preço total não são aceitos
como fonte de cálculo.

Retorna 201 em data com id, status completed, subtotal, discount, shipping, total,
items e created_at. Valores monetários da resposta e snapshots dos itens são
inteiros em centavos. Preço enviado é uma confirmação: divergência do banco
retorna 422. Falta de estoque, dados inválidos ou cupom inelegível também retornam
422 e não criam pedido nem baixam estoque.

A transação bloqueia usuário, produtos e variações; calcula valores no servidor,
baixa estoque e grava snapshots. Shipping é zero, explicitamente demonstrativo.
PRIMEIRACOMPRA vale 10% sobre produtos apenas quando a conta não tem pedido.
Não há processamento financeiro.

Reenviar a mesma request_key e conteúdo para a mesma conta retorna o pedido
existente em 200, sem nova baixa. Mesma chave com conteúdo diferente retorna 422.
Não existem endpoints de histórico/cancelamento neste escopo.

GET /api/demo-accounts é público apenas em local/testing (404 nos demais ambientes)
e retorna os dois acessos públicos de avaliação apresentados no login.

## Categorias importadas

Produtos também retornam category_path (lista, vazia para produtos sem caminho)
e demo_variants (booleano). As categorias disponíveis incluem a categoria principal
e cada item do caminho importado. Banner.category mantém uma única categoria de destino,
validada contra essa união. Filtros category e category_slug buscam tanto a categoria
principal quanto a presença no JSON category_path, combinados com os demais filtros.