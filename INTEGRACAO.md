# Integração — SIUPT Refeições

Este documento existe para quem for integrar este módulo no sistema real do SIUPT. Resume o que precisa de saber sem ter de ler todo o código.

## 1. Âmbito do módulo

Tudo o que está aqui pertence ao módulo **Refeições**, desenvolvido como estágio curricular. Todas as tabelas próprias seguem a convenção de nome `restaurante_*` — isto foi deliberado, para que fique imediatamente claro o que é deste módulo e o que pertence ao SIUPT.

A **única** tabela partilhada com o sistema existente é `users`, e é usada **só em leitura** (autenticação e consulta de nome/número). Nenhuma coluna foi acrescentada a `users` por este módulo.

## 2. Tabelas criadas por este módulo

| Tabela | Finalidade |
|---|---|
| `restaurante_tipo_refeicao` | Tipos de prato (Carne, Peixe, Vegetariano, Sopa, Sobremesa, Bebida, Menu Completo, e um tipo próprio por cada extra) |
| `restaurante_menu` | Pratos — com data (ementa semanal) ou sem data (extras sempre disponíveis); `RM_ATIVO` permite soft-delete de extras já comprados |
| `restaurante_preco_tipo_refeicao` | Histórico de preços por tipo, com data de início — permite alterar preços sem afetar pedidos já feitos |
| `restaurante_data_limite` | Prazo de compra por tipo de refeição (hora + dias de antecedência) |
| `restaurante_pedido` | Cabeçalho do pedido — QR code, código curto, estado pago/utilizado |
| `restaurante_compra` | Linhas de um pedido (um prato por linha, com o preço praticado no momento) |
| `restaurante_pagamento` | Histórico de tentativas de pagamento (simulado) |
| `restaurante_validacao` | Log de validações feitas por funcionários |
| `restaurante_avaliacao` | Avaliação do aluno (1-5 estrelas + motivo opcional para notas baixas) — associada ao **pedido**, não a um prato individual (ver Limitações) |
| `restaurante_transferencia` | Registo de transferências de refeições bem-sucedidas entre utilizadores |
| `restaurante_transferencia_tentativa` | Auditoria de tentativas de transferência falhadas |

O script completo de criação está em `siupt_refeicoes.sql`, na raiz do projeto, organizado por secções e com comentários.

## 3. Pontos de integração (onde mexer para ligar ao sistema real)

### 3.1 Autenticação — ficheiros a substituir

**Estado atual:** todo o login é uma simulação própria, feita só para desenvolvimento e testes — autentica contra a tabela `users` com `password_verify()` e cria uma sessão PHP simples (`$_SESSION['user_id']`, `user_nome`, `user_tipo`).

**Ficheiros a remover/substituir na integração real:**

| Ficheiro | O que fazer |
|---|---|
| `public/login.php` | **Remover por completo.** É só a página de login de simulação (formulário BI/CC + password, toggle Estudante/Colaborador). Substituir pelo mecanismo de login institucional do SIUPT. |
| `src/Support/Auth.php` | **Manter — não remover.** As funções `exigirLogin()`, `gerarCsrfToken()` e `verificarCsrfToken()` são usadas por todo o módulo e não dependem do `login.php`. Só a forma como `$_SESSION['user_id']` é preenchida precisa de mudar (ver abaixo). |
| Todas as chamadas a `header('Location: login.php?...')` | Ocorrem em `Auth.php` (`exigirLogin()`) sempre que a sessão expira ou o utilizador não tem permissão — apontar para a página de login real da UPT em vez de `login.php`. |

**O que fazer na prática:** qualquer mecanismo de autenticação real só precisa de garantir que, após autenticar o utilizador, preenche estas três variáveis de sessão exatamente como `login.php` já faz hoje:

```php
$_SESSION['user_id']   = $utilizador['U_ID'];   // ID da tabela users
$_SESSION['user_nome'] = $utilizador['U_NOME'];
$_SESSION['user_tipo'] = $tipo; // 'aluno' ou 'funcionario' — ver Database::perfilParaTipo()
```

A partir daí, todo o resto do módulo (`exigirLogin()` em `Auth.php`, e todas as páginas que a chamam) continua a funcionar sem alterações.

### 3.2 Pagamento — `src/Services/PagamentoService.php`

**Estado atual:** pagamento 100% simulado. `PagamentoService::processar()` não contacta nenhum gateway real — só regista a tentativa em `restaurante_pagamento` e marca o pedido como pago consoante o resultado simulado (botões "Simular aceite" / "Simular recusa" na UI).

**A fazer na integração real:** substituir o corpo de `PagamentoService::simular()` pela chamada real ao gateway MB WAY (ou outro método aprovado pela UPT). A assinatura do método (`processar(int $pedidoId, bool $sucesso, ?string $refGatewayBatch)`) e o contrato de retorno (`['status' => 'confirmado'|'falhado']`) foram desenhados para que só seja preciso trocar a implementação interna — os endpoints (`api/confirmar_pagamento_pedido.php`) e o frontend não precisam de mudar.

## 4. Como correr o projeto localmente

**Pré-requisitos:** PHP 8.3+ com extensão `pdo_sqlsrv` ativa, SQL Server (Express chega), Composer.

```powershell
composer install
```

Copiar `.env.example` para `.env` e preencher `DB_HOST`, `DB_NAME`, `DB_USER`/`DB_PASS` (vazios se usares autenticação Windows).

Correr `siupt_refeicoes.sql` no SSMS contra a base de dados criada.

Aceder via `http://localhost/siupt-refeicoes/public/login.php` (ajustar caminho conforme o servidor local).

**Nota sobre dependências:** o export de relatório em PDF (`api/exportar_relatorio_pdf.php`) depende do Dompdf, instalado via Composer. Sem correr `composer install`, esse endpoint específico falha com "Class not found" — o resto do sistema funciona normalmente sem esta dependência.

## 5. Limitações conhecidas (decisões conscientes, não bugs)

- **Avaliação por pedido, não por prato individual.** Um pedido com vários itens (ex: menu completo) só tem uma nota e um motivo, aplicados ao pedido inteiro. A UI de avaliação e o relatório assumem essa nota como representativa de todos os pratos desse pedido. Decisão de simplicidade — avaliar item a item aumentaria significativamente a complexidade do modal de avaliação para um ganho de informação considerado secundário.
- **Sem gestão de stock/limite de doses por prato.** A ementa não impede compras acima de um número máximo de porções disponíveis.
- **`login.php` é só simulação** — ver secção 3.1.

## 6. Testes

O módulo tem uma suite de testes automatizados (PHPUnit) cobrindo a lógica de negócio crítica: criação de pedidos, validação por QR code, transferências, cancelamentos, avaliações, preços históricos, prazos de compra, e gestão de extras.

**Como correr:**

```powershell
composer install
vendor\bin\phpunit --testdox
```

Os testes correm contra uma base de dados de teste isolada (`siupt_refeicoes_test`), separada da base de desenvolvimento/produção — nunca contra dados reais. Antes de correr pela primeira vez, cria essa base e aplica o mesmo esquema de `siupt_refeicoes.sql`.

Complementarmente, o módulo foi também validado manualmente, fluxo a fluxo, com dados de exemplo cobrindo todos os estados possíveis (pendente, ativo, utilizado, expirado, avaliações positivas e negativas, extras, transferências).