# Contrato REST

Contrato planejado para a API de produtos. Nesta etapa, ProductResource e o
tratamento de exceções estão implementados; os endpoints abaixo ainda não estão
registrados. Autenticação, CRUD e filtros serão implementados nas próximas etapas.

## Rotas e códigos de sucesso

| Método e rota | Acesso | Resposta |
|---|---|---|
| GET /api/products | Público | 200, coleção paginada |
| GET /api/products/{id} | Público | 200, produto |
| POST /api/products | Autenticado | 201, produto criado |
| PUT /api/products/{id} | Autenticado | 200, substituição dos campos editáveis |
| PATCH /api/products/{id} | Autenticado | 200, atualização parcial |
| DELETE /api/products/{id} | Autenticado | 204, sem corpo |

A autorização de escrita será aplicada no backend. Nenhuma rota de escrita
será disponibilizada sem essa proteção.

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
FormRequests validarão limites, URL, preço entre 0 e 99999999.99 com até duas casas
e estoque inteiro entre 0 e 4294967295.

## Listagem e paginação

Parâmetros planejados, combináveis por AND:

| Parâmetro | Regra |
|---|---|
| search | Busca parcial por nome, até 255 caracteres |
| category | Categoria exata, até 100 caracteres |
| min_price / max_price | Limites inclusivos; mínimo não pode superar máximo |
| in_stock | 1 filtra estoque > 0; 0 ou ausente não restringe disponibilidade |
| page | Inteiro >= 1; padrão 1 |
| per_page | Inteiro de 1 a 100; padrão 15 |
| sort | Allowlist: id, name, price, created_at; padrão id |
| direction | asc ou desc; padrão asc |

Ordenação terá desempate por id para paginação previsível. Parâmetros inválidos
receberão 422. Os links de paginação preservarão os filtros.

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

## Estado da implementação

- Disponível: GET /api/health, ProductResource e padronização de exceções.
- Planejado: rotas de produtos, FormRequests, autenticação, filtros e logs.
- Testes do contrato usam rotas temporárias exclusivas do processo PHPUnit;
  essas rotas não existem na aplicação em execução.
