# Frontend — experiência e implementação

[Projeto](../../README.md) · [Backend](../backend/README.md) · [Testes](../TESTING.md)

Vue 3 com Composition API/TypeScript, Vue Router, Axios e Vite. CSS próprio,
componentes reutilizados e composables compartilhados, sem biblioteca global de store.

## Telas

| Rota | Experiência |
|---|---|
| / | Apresentação, cupom, carrossel, busca e catálogo |
| /:categorySlug | Catálogo por categoria |
| /produtos/:id | Imagem, descrição, preço, estoque, seletores e Comprar |
| /login | Autenticação e acessos demonstrativos |
| /conta | Sessão e logout |
| /checkout | Endereço, CEP, resumo, cupom e finalização |
| /admin/products | Tabela e banners |
| /admin/products/new | Criação |
| /admin/products/:id/edit | Edição na mesma view do cadastro |
| Demais rotas | Não encontrada |

## Catálogo e navegação

Busca inicial com um campo; Busca avançada expande categoria/autocomplete, preços,
disponibilidade e quantidade por página. Enviar aplica filtros e reinicia página.
Ordenação oferece nome A–Z/Z–A e preço crescente/decrescente; home usa nome A–Z.
No painel, ordenação ocorre pelos cabeçalhos.

URL é a fonte dos filtros: histórico, reload e retorno do detalhe preservam consulta.
Categorias usam slug no caminho; demais filtros usam query. Termo não vazio em
search oculta apresentação e banners, mantendo cupom e controles.

Cards/detalhe reutilizam imagem com fallback, lazy loading, proporção natural e
sem Referer. Comprar só aparece no detalhe.

## Variações e carrinho

Botões circulares exigem cor e tamanho explícitos. Cor da imagem usa três faixas
diagonais e hover. Tamanhos sem estoque continuam visíveis, opacos e desabilitados;
trocar cor limpa tamanho.

Carrinho é um dialog lateral com botão flutuante e contador de unidades.
Combinações diferentes permanecem separadas. Quantidades respeitam estoque.
localStorage guarda IDs/quantidades; API atualiza preços e disponibilidade.
Carrinho é compartilhado entre sessões do navegador, sem sincronização entre dispositivos.

## Login e checkout

Guard consulta sessão antes de checkout/admin. Visitante vai ao login e retorna ao
checkout quando veio da compra. Cliente não acessa gerenciamento; API também protege.

ViaCEP preenche endereço, com timeout e alternativa manual.
Cupom mostra estimativa; elegibilidade e total final vêm do backend.
Finalizar envia UUID da tentativa, endereço e itens. Controles são bloqueados no envio.
Falha ambígua permite verificar a mesma tentativa; 422 mostra detalhes e atualiza carrinho.
Só a chave fica em sessionStorage, sem endereço.

Sucesso exibe número/total do pedido e remove unidades compradas. Compra é demonstrativa,
sem cobrança financeira ou frete real.

## Painel e banners

Tabela seleciona itens da página, inspeciona vários e exclui com confirmação.
Exclusões são independentes; falhas parciais preservam seleção dos pendentes.
ProductFormView deriva isEdit da rota. Sucesso retorna à tabela; erro mantém dados.

Banners usam URL, texto, categoria, posição e estado ativo. Carrossel manual limita
20 ativos e leva à categoria filtrada. Não há upload de imagens.

## Código

| Arquivo/diretório | Papel |
|---|---|
| [App.vue](../../frontend/src/App.vue) | Layout, carrinho flutuante e rodapé |
| [router.ts](../../frontend/src/router.ts) | Rotas, guards e títulos |
| [views](../../frontend/src/views/) | Telas |
| [components](../../frontend/src/components/) | Controles reutilizáveis |
| [composables](../../frontend/src/composables/) | Sessão, carrinho e consultas |
| [lib/api.ts](../../frontend/src/lib/api.ts) | Axios, CSRF, timeout e erros |
| [types](../../frontend/src/types/) | Contratos TypeScript |
| [style.css](../../frontend/src/style.css) | Identidade visual e responsividade |

Consultas anteriores são canceladas ao mudar rota/filtros; resultados permanecem
montados durante atualização. CSRF é renovado antes do login e, após 419, antes da próxima tentativa explícita; não há reenvio automático de escritas.
Há loading/vazio/erro, recuperação, labels, foco após navegação, atalho de conteúdo
e rolagem própria da tabela. Rodapé global traz autor e ícones sociais acessíveis.

## Executar e verificar

Compose executa npm ci e Vite em 0.0.0.0:5173. Proxy encaminha /api ao Laravel.

    docker compose -f backend/compose.yaml exec frontend npm run build

Verifica TypeScript e gera dist/. Não há suíte Vitest/E2E. Percursos e limitações
estão no [guia de testes](../TESTING.md).

Fora do Docker, use Node 24 e npm ci / npm run dev em frontend/.
Proxy padrão aponta para 127.0.0.1:8081; API_PROXY_TARGET altera destino.
Produção precisa de fallback index.html para rotas da SPA, preservando /api.