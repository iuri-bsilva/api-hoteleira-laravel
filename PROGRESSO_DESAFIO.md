# Progresso do desafio

Atualizado em 05/10/2026. O enunciado original está preservado em `DESAFIO.md`.

## Requisitos

- [x] Documentação de execução, importação, agendamento e uso da API.
- [x] Modelagem baseada nos XMLs, com migrations versionadas.
- [x] Modelo Workbench e exportação PNG revisados: 19 tabelas, 120 campos e 10 chaves estrangeiras. Consulte `MODELAGEM_BANCO.md`.
- [x] Comando Laravel para importar XML, com transação e idempotência.
- [x] Agendamento da importação no scheduler; Docker inclui processo dedicado.
- [x] CRUD de quartos por API REST.
- [x] Criação de reservas por API REST.
- [x] Respostas JSON, incluindo erros da API.

## Diferenciais implementados

- [x] Swagger / OpenAPI 3.0.
- [x] Testes automatizados PHPUnit e roteiro manual: última suíte local com 79 testes e 487 verificações.
- [x] Concorrência no MySQL isolado: 5 testes e 34 verificações, incluindo reservas, estoque e pagamentos.
- [x] Instalação limpa do código versionado: build, migrations, importação, seed e 24 verificações funcionais, em ambiente separado. Consulte `GUIA_DOCKER.md`.
- [x] Separação de responsabilidades entre controllers, services e models.
- [x] Docker com aplicação, MySQL, setup, scheduler e serviço de testes.
- [x] Verbos HTTP para consultas, criação, atualização e exclusão.
- [x] Segurança: Sanctum, expiração de tokens, permissões por hotel e limites de requisições.
- [x] Descontos manuais e cupons fixos/percentuais, com validade e mínimo.
- [x] Taxa de serviço e cálculos monetários em centavos.
- [x] Disponibilidade por quarto e por categoria, com alocação automática.
- [x] Categorias Standard, Luxo e Suíte criadas por seeder idempotente.
- [x] Usuários e permissões viewer/manager por hotel.
- [x] Registro de recebimentos, saldo e proteção contra pagamentos duplicados.
- [x] Logs de operações com identificador de requisição.

## Versionamento

Histórico local organizado por escopo com mensagens `feat:`, `build:`, `test:` e `docs:`. Bancos locais, credenciais, dependências e logs permanecem fora dos commits. Não há publicação em repositório remoto nesta etapa.

## Limites e verificações manuais

- A suíte normal usa SQLite em memória. A suíte separada `phpunit.mysql.xml` validou cinco cenários concorrentes no MySQL 8.4; o roteiro manual permanece para reprodução e testes adicionais.
- Gestão de pagamentos registra recebimentos administrativos: sem gateway, estorno ou conciliação automática com XML.
- Sem promoções automáticas, juros, limite de usos de cupons ou interface administrativa.
- Categorias têm cadastro e consulta; nesta etapa não têm alteração/exclusão.
- Cada quarto representa uma unidade física; o estoque da categoria depende dos quartos vinculados.
- O scheduler nativo exige configuração no sistema operacional; o Docker já executa `schedule:work`.

Documentação: `README.md`, `GUIA_DOCKER.md` e `TESTES_MANUAIS.md`.
