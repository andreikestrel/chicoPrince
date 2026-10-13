# Coleta e importação — Chico Rei

[Projeto](../../README.md) · [Backend](../backend/README.md) · [Coletor](../../scraper/chicorei/collect.py)

Integração opcional para obter uma amostra de produtos públicos e demonstrar o catálogo.
A aplicação também funciona com produtos fictícios, sem depender da loja externa.

## Duas etapas distintas

1. Python coleta HTML público e extrai Product/BreadcrumbList em JSON-LD. Gera JSON
   e relatório, sem escrever no banco.
2. Laravel valida a amostra e importa em transação; catálogo vazio sem amostra válida
   recebe fallback fictício.

Não usa API interna, navegador automatizado ou download de imagens.

## Executar no Docker

Com ambiente inicializado e migrations aplicadas, da raiz:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --limit=45

Inicializa catálogo vazio. Limite padrão 20; intervalo aceito 1–45.
Se o catálogo já existe, o comando padrão não coleta nem modifica dados.
Não há execução automática ao reiniciar Docker.

Somente coletar, sem importar:

    docker compose -f backend/compose.yaml exec --user sail laravel.test python3 /opt/chicorei/collect.py --limit 20

Fora do Docker, com Python 3:

    python scraper/chicorei/collect.py --limit 20

Saídas em scraper/chicorei/data/PASTA_DA_EXECUCAO: products.json, report.json e
robots.txt. Cada execução tem pasta própria, ignorada pelo Git.
O Compose monta o coletor em /opt/chicorei.

Retomar amostra anterior:

    docker compose -f backend/compose.yaml exec --user sail laravel.test python3 /opt/chicorei/collect.py --limit 45 --resume /opt/chicorei/data/PASTA_DA_EXECUCAO/products.json

--output CAMINHO também exporta o JSON ao caminho explícito, sobrescrevendo esse
arquivo de integração, sem apagar a pasta datada.

## Limites de acesso

O coletor consulta robots a cada execução e verifica permissão das URLs.
Acesso sequencial com pausa mínima de 5 segundos ou maior indicada pela origem,
timeout de 20 segundos por resposta, limite de 5 MiB e até 2 × limite de URLs visitadas.
Interrompe em 401/403/429 e redirecionamentos; não contorna bloqueios e não faz
repetições automáticas de falhas.

Links vêm do [plano](../../scraper/chicorei/collection-plan.json) e de páginas consultadas.
proposedLimits no plano é descritivo: mudar esse JSON não muda limites do código.
A retomada destina-se às saídas do próprio coletor.

Uma amostra menor é válida. Mudanças de HTML, rede e campos ausentes afetam a coleta.
45 produtos levam vários minutos; o processo chamado pelo Laravel tem timeout de 2200s.
Conteúdo e imagens pertencem à origem; a coleta técnica não transfere direitos de reutilização.

## Campos e mapeamento

| JSON coletado | Uso na aplicação |
|---|---|
| source / source_url | Origem chicorei e URL HTTPS; import_key é SHA-256 da URL |
| source_id / fetched_at | Identificação observada e instante UTC; preservados no JSON bruto |
| name / description | Nome/descrição, obrigatórios para importar |
| price / currency | String decimal com duas casas; importação exige BRL |
| image_url | URL HTTPS, sem baixar a imagem |
| category_path | Caminho de categorias; preservado como JSON e disponibilizado para filtros/banners |
| availability | Disponibilidade observada, não quantidade |
| stock / variants | Quantidade desconhecida null; variantes não extraídas |
| warnings | Ausências/ambiguidades para revisão |

A categoria principal usa o primeiro item útil após desconsiderar pais genéricos
Roupas/Acessórios/Casa, ou o primeiro item original. O caminho completo é preservado.
Importação aceita arquivo de até 10 MiB e valida campos antes de qualquer remoção.

## Fallback e complemento demonstrativo

Para catálogo fictício, independente de rede:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --offline

Cria 24 produtos sem imagem/variações e contas de avaliação.
Produtos fictícios precisam de variações cadastradas no painel para compra.

Para importados sem variantes, ProductDemoVariantsSeeder cria COR DA IMAGEM,
P/M/G em Camiseta/Camisa (ÚNICO nos outros tipos), 5 unidades por tamanho.
O marcador demo_variants informa a natureza fictícia na interface.
Isso não representa estoque real da Chico Rei e não altera o JSON bruto.

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan db:seed --class=ProductDemoVariantsSeeder

Reexecução não repõe estoque de produtos já complementados nem recria suas variantes removidas.

## Importar JSON e substituir fictícios intactos

Em catálogo vazio:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --limit=45 --file=/opt/chicorei/data/PASTA_DA_EXECUCAO/products.json

Em catálogo existente, simule explicitamente:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --limit=45 --file=/opt/chicorei/data/PASTA_DA_EXECUCAO/products.json --replace-demo --dry-run

Confira IDs candidatos e repita sem --dry-run somente se desejar executar.
Remove apenas produtos idênticos ao seeder, sem atualização, variantes ou origem.
Produtos próprios/editados e importados existentes são preservados.
Antes da remoção salva backup local em backend/storage/app/private/catalog-backups.
Falha de backup impede troca. Amostra inválida/vazia não remove produtos.
Criação/exclusão são transacionais; chave de origem evita duplicação.
Não há sincronização automática de edições/estoque.

Para completar category_path ausente de importações antigas:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:categories /opt/chicorei/data/PASTA_DA_EXECUCAO/products.json

Só preenche caminhos ausentes com correspondência de origem, sem alterar campos
de produto, estoque ou timestamps. Comandos de carga são exclusivos de local/testing.

## Testes

    docker compose -f backend/compose.yaml exec --user sail laravel.test python3 -m unittest discover -s /opt/chicorei -p test_collect.py -v

Quatro testes offline cobrem extração, ofertas ambíguas, robots e URLs.
Testes Laravel cobrem fallback, repetição, preservação de edições/variantes,
substituição seletiva, ausência de amostra válida e complemento de categorias.
Dados brutos, backups e caches não são versionados.