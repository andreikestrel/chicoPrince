# Coletor de amostra Chico Rei

Coletor independente em Python 3, usando somente a biblioteca padrão.
Extrai Product e BreadcrumbList em JSON-LD do HTML público. Não usa APIs internas,
não baixa imagens e não escreve no banco do Chico Prince.

## Executar

Na raiz do repositório:

    python scraper/chicorei/collect.py --limit 20

Para continuar uma amostra anterior sem consultar novamente suas páginas:

    python scraper/chicorei/collect.py --limit 20 --resume scraper/chicorei/data/PASTA_DA_EXECUCAO/products.json

Cada execução cria uma pasta própria em data/, ignorada pelo Git, contendo
products.json, report.json e robots.txt. Não sobrescreve execuções anteriores.
O relatório registra quantidade solicitada/coletada, URLs visitadas, falhas e
motivo de interrupção. Uma amostra menor também é um resultado válido.

## Comportamento

- Consulta robots antes dos produtos e avalia suas regras para cada URL.
- Acesso sequencial com intervalo mínimo de 5 segundos (ou maior, indicado na origem).
- Até 45 produtos base distintos (20 por padrão), até 2 × limite de URLs visitadas, timeout de 20 segundos,
  resposta máxima de 5 MiB e nenhuma repetição automática de falha.
- Interrompe em 401/403/429 e redirecionamentos; não contorna bloqueios.
- Usa os links observados do [plano](./collection-plan.json) e outros links de
  produto encontrados nas páginas consultadas.
- Preço decimal em string; quantidade de estoque desconhecida fica null.
  Disponibilidade e quantidade são informações diferentes.
- Variações de cor/tamanho ainda não são extraídas; essa ausência aparece em warnings.
- Campos ausentes não são inventados. Revisar os dados antes de planejar importação.

Os limites em proposedLimits no plano documentam os valores do coletor atual;
alterá-los no JSON não altera a implementação. --limit controla apenas a meta.
A retomada destina-se aos JSONs gerados pelo próprio coletor.

## Verificações offline

    python -m unittest discover -s scraper/chicorei -p test_collect.py -v

Cobertura: extração, preço, dados ausentes, ofertas ambíguas, regras de robots
com wildcard/exceção e deduplicação de URLs.

## Continuidade

[Pesquisa](./RESEARCH.md) e [mapeamento](./MAPPING.md) registram evidências e limites.
A amostra não representa estoque da loja local nem autorização de republicação
das imagens e textos. O comando Laravel catalog:bootstrap pode carregar uma amostra em catálogo vazio, com fallback fictício. Catálogos existentes são preservados; sincronização não está implementada.
Os seeders fictícios continuam independentes deste coletor.
## Docker

O Compose monta esta pasta em /opt/chicorei no Sail, que contém Python 3.
Na raiz do repositório:

    docker compose -f backend/compose.yaml exec --user sail laravel.test python3 /opt/chicorei/collect.py --limit 20

A coleta sozinha não escreve no banco. Para a carga inicial com fallback, após as
migrations, use catalog:bootstrap conforme o [README principal](../../README.md).
--output CAMINHO exporta também o JSON para integração; sobrescreve esse arquivo
explícito, enquanto a pasta datada de cada execução permanece preservada.