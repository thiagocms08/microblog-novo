# AGENTS.md — Guia do projeto Microblog

## Objetivo e visão geral

Microblog é um portal de notícias didático, desenvolvido em PHP orientado a objetos, com MySQL/MariaDB via PDO. Há uma área pública de leitura e uma área administrativa autenticada para gerenciar conteúdo e contas.

Este arquivo registra o contexto e os padrões atuais do repositório para orientar futuras alterações. Ao implementar algo, confira o código atual: algumas páginas ainda são provisórias e a documentação descritiva pode não refletir o estado implementado.

## Tecnologias e configuração

- PHP 8.5+ conforme o requisito documentado, com `pdo_mysql`, `mbstring` e `fileinfo`.
- MySQL/MariaDB; o banco esperado é `microblog_2026`.
- Composer é usado para autoload PSR-4 (`Microblog\\` → `src/`). Não há dependências de aplicação declaradas atualmente.
- Ambiente local documentado: Apache/XAMPP ou servidor PHP, mais MySQL/MariaDB.
- A configuração da conexão está em `src/Database/ConexaoBD.php`. Ela contém parâmetros locais fixos; ajuste-os para o ambiente em uso e não adicione credenciais reais ao repositório.
- Esquemas e dados de exemplo estão em `_materiais/*.sql`; leia o arquivo correspondente antes de alterar consultas ou estrutura de dados.
- Não há suíte de testes automatizados ou scripts de build/lint definidos no projeto. Para validar PHP alterado, use `php -l caminho\do\arquivo.php`; valide manualmente o fluxo afetado quando possível.

## Organização dos arquivos

- `src/Auth/`: sessão e autorização (`ControleDeAcesso`).
- `src/Database/`: conexão PDO (`ConexaoBD`).
- `src/Enums/`: `TipoUsuario` (`admin`, `editor`) e `Destaque` (`sim`, `nao`).
- `src/Helpers/`: normalização, escape, logs, upload e validação (`Utils`, `Validacoes`).
- `src/Models/`: modelos tipados (`Usuario`, `Categoria`, `Noticia`).
- `src/Services/`: consultas e operações PDO (`UsuarioServico`, `CategoriaServico`, `NoticiaServico`).
- `admin/`: páginas e ações do painel.
- `includes/`: cabeçalhos, rodapés e componentes HTML compartilhados. `cabecalho-admin.php` exige login.
- `css/`, `js/`, `admin/js/`, `images/`: recursos estáticos.
- `logs/erros.log`: log de exceções da aplicação; não o inclua em commits sem necessidade.
- `vendor/`: autoload gerado pelo Composer; não edite arquivos gerados manualmente.
- `_materiais/`: scripts SQL e material de apoio didático.

## Modelo de dados

O banco usa as tabelas principais:

- `usuarios`: `id`, `nome`, `email`, `senha` (hash), `tipo` (`admin` ou `editor`); o esquema de exemplo define e-mail único.
- `categorias`: `id`, `nome`.
- `noticias`: `id`, `data` (padrão `CURRENT_TIMESTAMP`), `titulo`, `texto`, `resumo`, `imagem`, `destaque` (`sim` ou `nao`), `usuario_id`, `categoria_id`.

Os IDs e a data das notícias são definidos pelo banco na inserção. `Noticia` aceita `id` e `data` nulos para representar uma notícia nova. Confirme constraints e foreign keys no SQL antes de presumir comportamento de exclusão ou relações.

## Convenções de código

- Use namespaces sob `Microblog\...`, alinhados ao diretório e ao autoload PSR-4.
- Classes e enums em PascalCase; métodos, propriedades, parâmetros e variáveis em camelCase. Mantenha nomes em português e o vocabulário já usado pelo módulo.
- Modelos têm propriedades privadas tipadas, construtor e getters (`getTitulo()`, `getUsuarioId()` etc.); não introduza setters ou propriedades públicas sem necessidade compatível com o padrão existente.
- Serviços são classes `final`, mantêm uma conexão PDO privada obtida por `ConexaoBD::getConexao()` no construtor e recebem modelos tipados para inserção/atualização.
- Serviços retornam arrays associativos de PDO para consultas (`fetch()`/`fetchAll()`), `?array` para busca individual e `void` para operações de escrita.
- Use prepared statements e parâmetros vinculados sempre que a consulta incorporar dados de entrada. Não concatene valores recebidos em SQL.
- Em serviços, capture `PDOException`, registre com `Utils::registrarLog($e)` e lance uma mensagem genérica para a camada de apresentação. Não exponha mensagens do banco ao usuário.
- Helpers e validações são métodos estáticos. `Validacoes` lança `InvalidArgumentException` para entradas inválidas; páginas normalmente exibem essa mensagem e tratam `Throwable` separadamente, registrando o erro e mostrando uma mensagem genérica.
- Use `Utils::normalizarEntrada()` para entradas de formulários e parâmetros de URL. Os modos observados são `texto` (padrão), `email` e `inteiro`; essa normalização não substitui validação de domínio.
- Normalizar não é escapar: escape todo valor dinâmico ao renderizar HTML usando `Utils::escaparHtml()`. Não remova tags do texto editorial na entrada; o escape deve ocorrer na saída.
- Para IDs de URL, o padrão é normalizar e validar com `Utils::verificarId()`.
- Preserve o uso de enums (`TipoUsuario`, `Destaque`) no domínio; converta para/desde string com `->value`, `::from()` ou `::tryFrom()` conforme o caso.
- Para senhas, use `password_hash` por meio de `Utils::codificarSenha()` e `password_verify()` para comparação. Nunca grave senhas em texto puro ou registre-as em logs.
- Em uploads, use `Utils::upload()` e os limites/formatos já implementados; verifique impacto em `images/` e no campo `noticias.imagem` antes de mudar o formato de armazenamento.
- Ao concluir uma ação de formulário, o padrão é redirecionar com `header(...)` e encerrar com `exit`.
- Páginas administrativas incluem `vendor/autoload.php`, verificam autorização antes da ação e usam `includes/cabecalho-admin.php`/`rodape-admin.php` para a estrutura visual.

## Permissões e fluxos atuais

- `ControleDeAcesso::exigirLogin()` protege páginas administrativas para usuários autenticados; a sessão armazena `id`, `nome` e `tipo`.
- `ControleDeAcesso::exigirAdmin()` exige administrador.
- `TipoUsuario::ADMIN` vê todas as notícias; `TipoUsuario::EDITOR` vê apenas as notícias associadas ao próprio ID em `NoticiaServico::listarTodos()`.
- O cadastro de notícias é acessível a usuário autenticado. O serviço de listagem retorna o autor para administradores, e `admin/noticias.php` apresenta os resultados em tabela.
- As operações de usuários e categorias estão protegidas como ações de administrador. A exclusão de usuário impede excluir a própria conta.
- A atualização do próprio perfil exige login.
- Atenção à diferença entre documentação e código: `README.md` descreve editores como autorizados a gerenciar categorias, mas as páginas atuais de categorias chamam `exigirAdmin()`. Preserve a autorização implementada, a menos que o requisito solicite explicitamente alterá-la.

## Estado funcional conhecido

- `UsuarioServico` e `CategoriaServico` implementam listagem, busca, inserção, atualização e exclusão.
- `NoticiaServico` implementa listagem por papel e inserção. Não presuma que outros métodos CRUD de notícia já existem.
- `admin/noticia-atualiza.php` ainda exibe um formulário estático, e `admin/noticia-exclui.php` ainda é uma tela provisória. Verifique a implementação antes de conectar ações ou afirmar que o CRUD de notícias está completo.
- A listagem administrativa de notícias está integrada ao serviço e diferencia admin/editor.
- As páginas públicas `index.php` e `noticia.php` ainda contêm conteúdo estático provisório. Embora o README descreva notícias públicas dinâmicas, confirme cada fluxo no código antes de estendê-lo.
- Validação de resumo limita o texto a 300 caracteres, embora o esquema de exemplo permita `varchar(500)`; respeite a regra da aplicação salvo requisito contrário.

## Banco, erros e manutenção

- A conexão PDO define `ERRMODE_EXCEPTION` e `FETCH_ASSOC`.
- `Utils::registrarLog()` grava exceções em `logs/erros.log` com horário de São Paulo. Erros de usuário não devem incluir detalhes internos; erros técnicos devem ser registrados conforme o padrão.
- O código usa `Throwable` nas páginas para tratar também erros além de `Exception`; evite capturas amplas sem log e mensagem explícita.
- Não altere arquivos gerados de `vendor/`. Se o mapeamento do Composer mudar, atualize `composer.json` e regenere o autoload com `composer dump-autoload`.
- Antes de alterar arquivos, confira `git status` e preserve mudanças preexistentes. Faça mudanças cirúrgicas e valide ao menos a sintaxe dos arquivos PHP tocados.
