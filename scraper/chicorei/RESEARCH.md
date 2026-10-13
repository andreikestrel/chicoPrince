# Investigação — TASK 2.1

Data: 2026-10-13. Resultado preliminar, não conclusão de viabilidade.

## Evidências

| Fonte | Resultado |
|---|---|
| [Página inicial](https://chicorei.com/) | Conteúdo público acessível pela ferramenta de leitura web. |
| [robots.txt](https://chicorei.com/robots.txt) | HTTP 200 em consulta direta; User-agent * com Crawl-delay 5. |
| [Categoria de camisetas](https://chicorei.com/camiseta/) | Página pública localizada; leitura textual não confirmou conjunto completo de produtos. |
| [Produto de referência](https://chicorei.com/camiseta/camiseta-viralata-caramelo-17930.html) | URL localizada; leitor textual retornou principalmente estrutura institucional. Preço, variantes e estoque não foram confirmados. |
| [Política de privacidade](https://chicorei.com/politica-de-privacidade) | Trata dados pessoais de clientes; não foi usada como autorização de coleta/reutilização do catálogo. |

robots informa sitemap em https://chicorei.com/sitemap-xml.php, explicitamente permitido.
O sitemap não foi baixado. Entre as restrições observadas estão /modules/, /phpexe/,
/perfil/, /config/, /tools/ e padrões de arquivos PHP (com exceção do sitemap).
Consultar o arquivo completo e atual antes de implementar; não transformar este resumo
em uma allowlist de acesso. Nenhum dado pessoal foi coletado.

Busca limitada em fontes oficiais não localizou documentação de API de catálogo nem
termos específicos de reutilização. Isso não comprova que não existam.

## Próxima investigação

- Inspecionar uma página renderizada e seu HTML/dados estruturados públicos.
- Determinar se a extração exige JavaScript; o leitor textual incompleto é apenas
  indício, não prova dessa necessidade.
- Verificar condições de acesso/reutilização aplicáveis à amostra.
- Registrar campos realmente disponíveis, origem, moeda, preço vigente e variantes.
- Não confundir disponibilidade de venda com quantidade física em estoque.
- Decidir: API documentada, HTML público adequado ou interromper a importação.

Nenhum endpoint interno foi adivinhado ou consultado; nenhuma coleta em lote executada.
## Atualização após coleta autorizada
A consulta direta ao HTML confirmou Product e BreadcrumbList em JSON-LD.
Foi possível extrair nome, descrição, imagem, SKU, preço, moeda e disponibilidade
sem JavaScript e sem consultar APIs internas. A categoria pública forneceu 38
URLs de produtos candidatas; isso não equivale a 38 produtos coletados.
Cor/tamanho e estoque quantitativo não foram extraídos.
Termos específicos de reutilização continuam não confirmados; saída local para revisão.
Os registros anteriores descrevem a investigação inicial com leitor textual.