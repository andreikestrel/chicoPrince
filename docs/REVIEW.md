# Revisão de segurança e desempenho

Escopo: catálogo, sessão, CRUD e painel de produtos. Revisão de código e verificações
funcionais locais; não representa auditoria independente nem teste de carga.

## Controles verificados

| Área | Evidência / decisão |
|---|---|
| Escrita | Rotas POST/PATCH/PUT/DELETE com auth:sanctum; FormRequest autoriza usuário presente. |
| Sessão | Cookies, regeneração no login e invalidação no logout; sem Bearer nem credenciais em armazenamento web. |
| CSRF | Middleware web ativo; POST real sem header retorna 419 mesmo autenticado. Cookie existente evita bootstrap redundante, sem pular validação. |
| Entrada | FormRequests, limites numéricos/textuais, allowlist de ordenação, binding numérico e consultas parametrizadas. |
| Saída | Resource explícito; erros internos ocultos; templates Vue escapam texto sem v-html. |
| Imagens | API aceita URL HTTP(S); componente também filtra protocolos, não envia Referer, usa lazy loading e fallback. Backend não baixa URLs. |
| Lote | Confirmação com IDs/nomes; seleção só na página; chamadas sequenciais; resultados individuais e repetição das falhas. |
| Consultas | Listagem faz count e consulta paginada; sem relações carregadas por item ou N+1 no controller. Categoria indexada. |
| Logs | Ação, IDs de produto e usuário; sem payload, senha ou token; retenção operacional de 14 dias. |
| Repositório | Configuração privada, logs e documentos internos fora do rastreamento Git. |

O termo admin na rota não define uma função de administrador. Todos os usuários
autenticados podem gerenciar todos os produtos, conforme o modelo atual do teste.

## Verificações executadas

- Suíte no MySQL exclusivo de testes: 33 testes, 226 assertions, todos aprovados.
- Build TypeScript/Vite.
- Navegador: bloqueio de gerenciamento para visitante, CRUD e exclusão parcial
  com falha 503 simulada, seguida de nova tentativa só do item pendente.
- Responsividade básica, navegação por teclado, foco e estados de carregamento/erro.

## Limitações e decisões

- A busca parcial por nome pode varrer a tabela; um índice B-tree simples não resolve
  LIKE com curinga inicial. Não foi adicionado índice sem plano de consulta e carga representativa.
- Offset/paginação com count atende ao escopo atual; grandes bases exigem medição própria.
- Exclusão em lote não é transacional; fechar/recarregar durante a operação pode interromper
  o lote. A navegação interna é bloqueada enquanto a operação está em andamento.
- Imagens externas ainda revelam o IP do navegador ao servidor da imagem. Não há proxy/upload.
- Não há papéis, ACL por produto ou trilha transacional de auditoria.
- O servidor de desenvolvimento não equivale a implantação de produção. HTTPS, cookies
  seguros e configuração do servidor/reverse proxy devem ser definidos na implantação.
- Testes PHPUnit não exercitam CSRF; a proteção foi verificada separadamente por HTTP.
- Não foi adicionada suíte automatizada de frontend; validação humana e leitor de tela
  completo ainda são necessários.

Medições e ajustes específicos da estação de desenvolvimento são mantidos fora
da documentação pública. As mudanças de interface evitam bootstrap CSRF repetido,
consultas ao abandonar views e desmontagem dos cards durante atualização.
