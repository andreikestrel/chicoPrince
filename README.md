# Chico Prince

**Vista sua própria história.**

Catálogo e experiência de compra desenvolvidos por **Andrei Kestrel** para um teste
técnico Full Stack. API Laravel, banco MySQL e interface Vue: o visitante encontra
produtos, o cliente conclui uma compra demonstrativa e o administrador gerencia
catálogo, variações e banners.

[Backend e arquitetura](docs/backend/README.md) · [Frontend](docs/frontend/README.md) ·
[Coleta e importação](docs/scraping/README.md) · [Contrato REST](docs/API.md) · [Testes](docs/TESTING.md)

## Funcionalidades

| Área | O que está implementado |
|---|---|
| Catálogo público | Cards com imagens proporcionais, preço em reais, categoria, disponibilidade e fallback. Detalhes com descrição completa. |
| Busca | Busca por nome, filtros avançados por categoria, preço e disponibilidade, paginação configurável e ordenação por nome ou preço nas duas direções. |
| Navegação | Filtros compartilháveis pela URL, categorias em caminhos próprios, histórico e retorno preservando a consulta. Buscar um termo oculta introdução e banners. |
| Categorias | Autocomplete com categorias cadastradas e categorias adicionais da coleta; banners podem apontar para qualquer uma delas. |
| Variações | Cor e tamanho obrigatórios, botões circulares e seleção explícita. Opções indisponíveis visíveis e desabilitadas; estoque por combinação. |
| Carrinho | Modal lateral, botão flutuante com contador, inclusão somente no detalhe, alteração de quantidade, remoção e persistência no navegador. |
| Checkout | Login obrigatório com retorno à compra, endereço manual ou preenchido por CEP, resumo, cupom e confirmação. |
| Pedidos | Finalização demonstrativa, status concluído, totais calculados no servidor, baixa transacional de estoque e proteção contra repetição da mesma tentativa. |
| Cupom | PRIMEIRACOMPRA: 10% nos produtos do primeiro pedido da conta, validado no backend. |
| Autenticação | Sessão por cookie, CSRF, login/logout, restauração de sessão e contas de avaliação na tela de login. |
| Permissões | Cliente compra; administrador gerencia produtos e banners. A API aplica as permissões independentemente da interface. |
| Painel | Tabela com imagem e campos do produto, filtros, ordenação, seleção múltipla, visualização conjunta e exclusão individual/em lote com confirmação. |
| Cadastro e edição | Uma única view para criar/editar, validação por campo, variações e retorno à tabela após salvar. |
| Banners | Imagem por URL, texto, categoria, posição e ativação; carrossel manual de até 20 banners ativos. |
| Carga inicial | 24 produtos fictícios sem dependência externa ou coleta opcional de até 45 produtos da Chico Rei, fallback e complemento demonstrativo de variações. |
| Qualidade | FormRequests, Resources, erros JSON padronizados, logs de CRUD, testes de backend/coletor, build TypeScript, estados de carregamento/vazio/erro e acessibilidade básica. |

**A compra é demonstrativa:** não há cobrança, integração financeira ou cálculo real
de frete. A interface informa isso antes da conclusão. O frete demonstrativo é zero.

## Arquitetura

| Camada | Tecnologias |
|---|---|
| Backend | PHP 8.4, Laravel 12, Sanctum, Eloquent |
| Banco | MySQL 8.4 |
| Frontend | Vue 3, Composition API, TypeScript, Vue Router, Axios, Vite |
| Coletor | Python 3, biblioteca padrão, HTML público com JSON-LD |
| Ambiente | Docker Compose, Laravel Sail, Node 24 |

    Navegador → Vue/Vite → /api → Laravel → MySQL
                                     ↑
                       JSON validado da coleta opcional

O proxy do Vite mantém a API na mesma origem em desenvolvimento. Composer e npm
possuem locks versionados. Este ambiente não é uma configuração de produção.

## Como rodar

### Pré-requisitos

- Git e Docker com Compose, usando containers Linux.
- Windows: Docker Desktop com WSL2.
- Portas 5173 e 8081 disponíveis.
- Internet na primeira instalação para imagens e dependências. PHP, Composer e
  Node não precisam estar instalados no host.

Clone este repositório pela URL disponibilizada e entre na pasta clonada.
Execute todos os comandos abaixo na **raiz do projeto**. O bootstrap usa PWD e
funciona no PowerShell e em shells POSIX.

### 1. Preparar ambiente e dependências

    docker run --rm --mount "type=bind,source=${PWD}/backend,target=/var/www/html" -w /var/www/html laravelsail/php84-composer:latest sh -lc 'php scripts/init-env.php && composer install --no-interaction'

O script gera configuração local, chave da aplicação e senhas aleatórias.
Valores existentes são preservados. Não copie configuração privada de outra instalação.

### 2. Iniciar os containers

    docker compose -f backend/compose.yaml config --quiet
    docker compose -f backend/compose.yaml up -d --build

O primeiro build pode demorar. O frontend executa npm ci ao iniciar.
Aguarde o MySQL saudável e o Vite pronto nos logs.

### 3. Preparar o banco

    docker compose -f backend/compose.yaml exec laravel.test chown -R sail:sail .env storage bootstrap/cache
    docker compose -f backend/compose.yaml restart laravel.test
    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan migrate

### 4. Escolher a carga inicial

**Sem depender da coleta externa:**

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --offline

Cria 24 produtos fictícios e contas de avaliação. Esses produtos não têm imagens
nem variações automáticas: entre como administrador e cadastre cor/tamanho/estoque
para experimentar a compra, ou crie um produto com variações.

**Alternativa, para iniciar com produtos coletados:**

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan catalog:bootstrap --limit=45

Use esta alternativa no lugar da carga offline, em catálogo vazio. Dados válidos
são importados com variações demonstrativas. Sem produtos válidos, usa os fictícios.
A meta não garante 45 itens: páginas incompletas são ignoradas.
A coleta pode levar vários minutos. Imagens permanecem URLs externas.

Se já houver produtos, o comando padrão preserva o catálogo e não coleta novamente.
Substituição seletiva e opções estão no [guia da coleta](docs/scraping/README.md).

### 5. Acessar

| Endereço | Uso |
|---|---|
| http://localhost:5173 | Aplicação |
| http://localhost:5173/login | Entrar |
| http://localhost:5173/admin/products | Painel, exige administrador |
| http://localhost:8081/api/health | Saúde da API e conexão MySQL |

| Papel | E-mail | Senha de demonstração |
|---|---|---|
| Administrador | admin@chicoprince.com | 123456 |
| Cliente | teste@chicoprince.com | 654321 |

Contas públicas de avaliação existem somente em local/testing. O seeder restaura
essas duas credenciais ao executar. Há também um usuário legado configurável com
senha aleatória no ambiente local.

## Uso diário

    docker compose -f backend/compose.yaml ps
    docker compose -f backend/compose.yaml logs --tail=100
    docker compose -f backend/compose.yaml stop
    docker compose -f backend/compose.yaml up -d

Remover containers preservando dados:

    docker compose -f backend/compose.yaml down

Não use down -v para parada normal: ele apaga volumes. MySQL não publica porta no host.
Variáveis, atualização e diagnóstico estão no [guia do backend](docs/backend/README.md).

## Verificar o projeto

    docker compose -f backend/compose.yaml exec --user sail laravel.test php artisan test
    docker compose -f backend/compose.yaml exec frontend npm run build
    docker compose -f backend/compose.yaml exec --user sail laravel.test python3 -m unittest discover -s /opt/chicorei -p test_collect.py -v

A suíte padrão usa SQLite em memória. O [guia de testes](docs/TESTING.md) explica
MySQL dedicado e o roteiro manual de avaliação. A instalação limpa foi executada
com dependências novas, carga offline, compra no navegador e persistência após recriar containers;
resultados e limites estão registrados nesse guia.

## Limites do escopo

- Sem pagamento real, frete real, vale-presente, cancelamento ou histórico visual de pedidos.
- Carrinho local ao navegador, compartilhado entre sessões nesse navegador.
- Preço e imagem pertencem ao produto; variações controlam cor, tamanho e estoque.
- Imagens por URL, sem upload/download/proxy; disponibilidade depende da origem.
- Estoque e variações complementados na importação são fictícios e identificados como demonstração.
- Exclusão em lote usa chamadas individuais, sem atomicidade do conjunto.
- Coleta não sincroniza automaticamente edições ou estoque da origem.
- Sem suíte automatizada frontend, teste de carga ou configuração de produção.
- Parte das mensagens detalhadas de validação usa o idioma padrão do Laravel.

## Autor

**Andrei Kestrel** · [GitHub](https://github.com/andreikestrel) ·
[LinkedIn](https://www.linkedin.com/in/barbosaandrei/)