# Proposta de mapeamento — TASK 2.2

Rascunho para validar após a investigação. Não é contrato implementado.

| Campo de saída | Regra proposta |
|---|---|
| source | Identificador fixo chicorei. |
| source_url | URL pública canônica HTTPS da página; preservar para rastreabilidade. |
| source_id | ID estável somente quando confirmado pela fonte; caso contrário null. |
| fetched_at | Momento real da coleta, UTC. |
| name / description | Valores observados; descrição em texto, sem HTML executável. Ausência vira null e pendência. |
| image_url | URL HTTPS observada; sem download ou incorporação automática. |
| price / currency | Decimal em string e moeda explícita; não confundir parcela, preço anterior ou preço condicionado a cupom com preço vigente. Ausência/ambiguidade vira null. |
| category | Categoria observada; conciliar com as categorias locais na importação. |
| variants | Combinações reais de cor/tamanho confirmadas na fonte, sem produto cartesiano artificial. |
| variants[].stock | Quantidade apenas se informada explicitamente; null se só houver disponibilidade. |
| variants[].availability | Estado textual observado, independente da quantidade. |
| warnings | Campos ausentes, conflitos ou informações ambíguas para revisão. |

## Integração futura

- JSON de coleta pode conter null; não enviar automaticamente esses registros ao
  endpoint de produtos, que exige determinados campos e estoque inteiro.
- Estoque do fornecedor não é estoque da loja local. Quantidades desconhecidas
  precisam de decisão explícita; não inventar estoque para habilitar compras.
- Deduplicar por origem + ID confirmado; alternativa: origem + URL canônica.
  Nome não serve como identificador.
- Persistir vínculo de origem separado dos campos de produto na integração futura;
  nenhuma migration de importação foi criada.
- Reexecução deve informar criados/inalterados/conflitos, sem sobrescrever edições
  locais automaticamente nem excluir produtos que sumiram da fonte.
- Falha de coleta não deve modificar banco nem afetar catálogo/checkout.
- Manter seeders fictícios independentes de rede, sem incorporar conteúdo real
  ou imagens da origem aos dados padrão.
## Integração implementada
catalog:bootstrap valida dados obrigatórios, ignora registros incompletos e importa
em transação. import_key é SHA-256 da URL; source_url preserva a referência.
--replace-demo permite remover somente fictícios intactos, com backup e simulação.
Valores de cor/tamanho/estoque criados pelo ProductDemoVariantsSeeder são sintéticos
e identificados por demo_variants. Eles não alteram o JSON bruto da coleta.
Produtos já importados e variações existentes são preservados nas reexecuções.
Os tópicos anteriores registram o desenho inicial; esta seção descreve a implementação.