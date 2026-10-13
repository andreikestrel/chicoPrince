# Testes e roteiro de avaliação

[Projeto](../README.md) · [Backend](backend/README.md) · [Frontend](frontend/README.md)

## Verificações automatizadas

Com containers iniciados, da raiz:

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan test
    docker compose -f backend/compose.yaml exec frontend npm run build
    docker compose -f backend/compose.yaml exec --user sail laravel.test python3 -m unittest discover -s /opt/chicorei -p test_collect.py -v

PHPUnit usa SQLite em memória por padrão; o caso de UNSIGNED é específico do MySQL
e fica ignorado no SQLite. O build verifica TypeScript e gera os assets do Vite.

### MySQL isolado

Crie banco dedicado e dê ao usuário de aplicação permissão nesse banco adicional.
Em cliente MySQL autenticado como administrador, adapte o usuário se necessário:

    CREATE DATABASE chico_prince_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON chico_prince_testing.* TO 'chico_prince'@'%';

Execute:

    docker compose -f backend/compose.yaml exec --user sail -e DB_CONNECTION=mysql -e DB_DATABASE=chico_prince_testing laravel.test php artisan test

RefreshDatabase recria tabelas: nunca aponte esse comando ao banco com dados de uso.

| Área | Cobertura |
|---|---|
| Sessão | Login, senha inválida, logout, visitante, rate limit, rejeição de Bearer |
| Permissões | Cliente/admin, gerenciamento protegido e contas demonstrativas |
| Produtos | CRUD, 404, campos obrigatórios, URL, valores e limites |
| Listagem | Filtros combinados, busca literal, paginação e desempate |
| Contrato | Recursos, coleções e erros HTTP padronizados |
| Variações | Combinação única, IDs estáveis e estoque agregado |
| Categorias/banners | Slugs, colisões, categorias importadas, ativação, CRUD e limites |
| Pedidos | Totais no servidor, estoque/preço, cupom, repetição e payload divergente |
| Carga | Fallback, reexecução, preservação de edições e complemento de categorias |
| Logs | Ações com identificadores, sem log de sucesso quando falha |

CSRF é desabilitado no ambiente PHPUnit. Teste-o também por HTTP real:
escrita sem token retorna 419; com token, visitante recebe 401, cliente recebe 403
no gerenciamento e administrador consegue criar/editar/excluir.

## Instalação limpa

1. Clone o código em outra pasta, sem copiar ambiente, vendor, node_modules ou banco.
2. Siga o README desde o bootstrap; gere configuração própria.
3. Se mantiver outra instalação ligada, use portas diferentes e nome Compose distinto.
   Prefixe todos os comandos Compose desse teste com -p chico-prince-validation.
4. Para este roteiro sem dependência da coleta, substitua o comando combinado de migrations/importação do README por duas chamadas Artisan: php artisan migrate e php artisan catalog:bootstrap --offline, no serviço laravel.test e como usuário sail. Confirme catálogo de 24 produtos e as contas. O caminho padrão do README tenta a coleta online; sua validação está registrada ao final deste guia.
5. Rode build e testes. Execute o roteiro manual abaixo.
6. Pare/recrie somente os containers desse projeto, sem remover volumes; confira persistência.

Cache de imagens Docker pode ser reaproveitado. Isso não comprova execução sem cache
ou em outro sistema operacional. Teste de coleta online é opcional e depende da origem.

## Roteiro manual

### Catálogo e navegação

- Abrir home como visitante: cards, preço, fallback, cupom e layout móvel.
- Buscar nome, combinar categoria/preços/estoque e verificar resultado vazio.
- Ordenar nas quatro opções, paginar e conferir URL, reload, histórico e retorno do detalhe.
- Confirmar que busca por termo oculta introdução/banners e limpar restaura.

### Administrador

- Entrar com conta admin; abrir Gerenciar produtos.
- Criar um produto de teste com cor/tamanho/estoque, validar campo obrigatório e salvar.
- Editar preço/variações; confirmar retorno à tabela.
- Selecionar vários itens, visualizar, cancelar exclusão e excluir apenas dados de teste.
- Criar banner com categoria existente, verificar destino, editar e desativar/excluir.
- Sair; visitante não tem gerenciamento e cliente não pode administrar.

### Compra

- Abrir produto com variantes; Comprar bloqueado até escolher cor e tamanho.
- Tamanho sem estoque visível e desabilitado.
- Adicionar, alterar quantidade, remover e recarregar carrinho.
- Como visitante, continuar ao checkout deve ir ao login e voltar após autenticar.
- Preencher endereço; testar CEP válido/inválido e preenchimento manual.
- Aplicar PRIMEIRACOMPRA no primeiro pedido, verificar total e finalizar.
- Conferir confirmação, carrinho atualizado, estoque baixado e pedido persistido.
- Segunda compra com o mesmo cupom deve ser recusada, sem baixar estoque.
- Cancelar/remover dados temporários do teste apenas quando não houver pedido associado;
  pedidos usam snapshots e não têm fluxo de cancelamento implementado.

## Evidências e limites

Última suíte completa anterior à reorganização documental: 56 testes / 444 assertions
em MySQL isolado. Quatro testes Python offline e build TypeScript/Vite passaram.
Percurso real de login→checkout→pedido, estoque e permissões foi verificado no navegador.
Dados temporários usados nesses testes foram removidos.

### Instalação limpa verificada em 2026-10-13

Clone local do código versionado, sem copiar configuração, dependências ou banco.
Instalação pelo fluxo deste README, com portas/projeto Compose separados e sem
override local de desempenho. Cache de imagens Docker foi reutilizado.

- Composer instalou 112 dependências pelo lock; npm ci e build passaram.
- Nove migrations aplicadas; carga offline criou 24 produtos e três usuários.
- SQLite: 55 testes / 442 assertions aprovados, um caso MySQL ignorado.
- Python: quatro testes offline aprovados.
- API/proxy e conexão MySQL saudáveis.
- Navegador: login admin, criação/edição com variação, retorno à tabela, carrinho,
  visitante→login cliente→checkout, cupom, pedido completed e baixa de estoque.
- Pedido de teste: 4990 − 499 = 4491 centavos; estoque 3→2; carrinho esvaziado.
- Cliente recebeu 403 no gerenciamento; escrita sem CSRF retornou 419.
- Banner criado, destino por categoria validado e desativação refletida na API pública.
- Busca vazia ocultou apresentação/banners, preservando cupom; layout móvel sem overflow.
- down/up sem remover volumes preservou produto, usuários, pedido, estoque e banner.

Foi identificada e corrigida uma falha de recuperação de CSRF: um cookie antigo
podia causar 419 repetidamente no login. Login agora renova o token; após 419,
outras operações renovam antes da próxima tentativa explícita. Sem reenvio automático.
A correção foi aplicada ao código principal e ao clone, com novo build e teste de
login usando cookie deliberadamente inválido, além do ciclo logout/login.

A etapa inicial acima usou carga offline. A coleta online foi validada posteriormente em banco vazio, conforme seção abaixo. Não houve nova execução da suíte MySQL dedicada
nesse clone: os percursos reais usaram MySQL e a suíte padrão usou SQLite.
Não há suíte automatizada frontend, auditoria independente, teste completo de leitor
de tela ou ensaio de carga concorrente. Testes aprovados não garantem ausência de falhas.
### Coleta online no clone — 2026-10-13

Executado catalog:bootstrap --limit=45 pelo Sail, com nove migrations aplicadas
em banco vazio e volumes próprios. Sem --offline, --file ou --replace-demo.

- Relatório: 45 solicitados, 45 coletados, 45 URLs visitadas, nenhuma falha
  e nenhum motivo de interrupção.
- Importação: 44 produtos válidos; Camiseta Through The Valley foi ignorada
  por ausência de descrição. Nenhum produto fictício foi criado.
- Banco: 44 origens distintas, 44 caminhos de categoria, 132 variações
  demonstrativas, três usuários de avaliação/legado e nenhum pedido.
- Navegador: catálogo com 44 produtos, uma imagem externa conferida carregada
  em 1800×2700 e botão Comprar habilitado após selecionar cor/tamanho no detalhe.
  Não foi verificado o carregamento individual de todas as imagens.
- Reexecutar o mesmo bootstrap preservou o catálogo e terminou sem nova coleta.
- Nenhuma alteração de implementação foi necessária para a coleta online.

O coletor gera JSON sem gravar banco; a escrita acima é feita separadamente pelo
comando Laravel. As variações/quantidades são dados fictícios de suporte,
não quantidades extraídas da origem. A disponibilidade futura da fonte não é garantida.