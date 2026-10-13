# Verificações da API

Da raiz, com os containers iniciados:

```sh
docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan test
```

A configuração padrão usa SQLite em memória. RefreshDatabase recria as tabelas
nesse banco de teste; o banco de desenvolvimento não deve ser usado pela suíte.
O teste de estoque UNSIGNED é exclusivo de MySQL e fica ignorado no SQLite.

## Executar com MySQL

Prepare um banco vazio dedicado, por exemplo `chico_prince_testing`, e dê ao usuário
de aplicação permissão apenas sobre esse banco adicional. No cliente MySQL,
conectado como administrador, adapte o usuário se alterou a configuração padrão:

```sql
CREATE DATABASE chico_prince_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON chico_prince_testing.* TO 'chico_prince'@'%';
```

Então execute:

```sh
docker compose -f backend/compose.yaml exec --user sail -e DB_CONNECTION=mysql -e DB_DATABASE=chico_prince_testing laravel.test php artisan test
```

Esse comando apaga/recria tabelas do banco indicado. Use exclusivamente um banco
reservado para testes. As credenciais continuam vindo do ambiente privado.

## Cobertura atual

| Área | Casos |
|---|---|
| Sessão | Login, credencial inválida, logout, visitante, rate limit, rejeição de Bearer |
| Produtos | Leitura pública, bloqueio de escrita anônima, POST/PUT/PATCH/DELETE, 404 |
| Validação | Obrigatórios, URL, preço/estoque inválidos e limites aceitos |
| Listagem | Filtros combinados, extremos inclusivos, curingas literais, paginação e desempate |
| Contrato | Resource, coleção, erros 401/403/404/405/422/429/500 e headers relevantes |
| Dados | Seeders repetíveis, preservação de edições, decimal e estoque UNSIGNED |
| Logs | Três ações com identificadores; falhas não registram sucesso |

CSRF é desabilitado pelo framework no ambiente PHPUnit. A proteção foi verificada
separadamente por HTTP real com cookies através do proxy Vite: sem token, 419;
com token e sem sessão, 401; sessão válida permite CRUD; logout bloqueia novas escritas.

Resultado atual: SQLite 32 passaram / 224 assertions / 1 ignorado; MySQL 33 passaram
/ 226 assertions. São verificações do escopo implementado, não garantia de ausência
de falhas nem testes completos da interface futura.
