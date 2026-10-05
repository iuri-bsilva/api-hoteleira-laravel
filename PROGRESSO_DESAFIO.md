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

Histórico organizado por escopo com mensagens `feat:`, `build:`, `test:` e `docs:` e enviado ao repositório `iuri-bsilva/api-hoteleira-laravel`. Bancos locais, credenciais, dependências e logs permanecem fora dos commits.

## Refatoração de arquitetura

- [x] Pagamentos separados em Form Request, controller, service e repository, com interfaces registradas no container Laravel e DTO de resultado.
- [x] Controller de pagamentos concentra apenas coordenação e resposta HTTP; saldo, idempotência e transação ficam no service, consultas e gravação no repository.
- [x] Regressão após a refatoração: 79 testes funcionais / 487 verificações em SQLite e 5 testes concorrentes / 34 verificações no MySQL temporário passaram.
- [x] Quartos separados em Requests de cadastro, alteração e disponibilidade, controller, service e repository, com interfaces registradas no container.
- [x] Regressão de quartos: 80 testes funcionais / 496 verificações e 5 testes concorrentes MySQL / 34 verificações passaram, incluindo novo caso de rollback na transferência com categoria incompatível.
- [x] Gestão de cupons separada em Requests, controller, service e repository, com contratos no container Laravel; aplicação do desconto na reserva continua no serviço de reservas.
- [x] Regressão de cupons: 81 testes funcionais / 503 verificações passaram, incluindo a proteção dos campos comerciais no PATCH de ativação.
- [x] Categorias e disponibilidade agrupada separadas em Requests, controller, service e repository, com interfaces e validação compartilhada de período com quartos.
- [x] Regressão de categorias: 82 testes funcionais / 517 verificações passaram, incluindo disponibilidade para a estadia inteira e datas enviadas como arrays.
- [x] Reservas separadas em Request, service e repository com interfaces; validação compartilhada com XML e persistência transacional preservando pagamentos da API.
- [x] Regressão funcional de reservas: 84 testes / 531 verificações passaram, incluindo rejeição de datas em arrays sem gravação parcial.
- [x] Regressão concorrente após separar as consultas de reservas: 5 testes MySQL / 34 verificações passaram.
- [x] Autenticação separada em LoginRequest, controller, service e repository com contratos registrados no container, mantendo token de oito horas e logout apenas da sessão corrente.
- [x] Regressão da autenticação: 85 testes / 540 verificações passaram, incluindo sessões em dois dispositivos e validade do token emitido.
- [x] Listagem de hotéis separada em controller, service e repository com interfaces, preservando vínculos, pivot e paginação.
- [x] Revisão dos sete controllers e rotas: sem queries, cálculos, transações ou validações inline; 85 testes / 540 verificações passaram após a última refatoração.
- Comandos administrativos, models e seeders mantêm suas responsabilidades específicas. O roteiro de regressão está em `TESTES_MANUAIS.md`.

## Limites e verificações manuais

- A suíte normal usa SQLite em memória. A suíte separada `phpunit.mysql.xml` validou cinco cenários concorrentes no MySQL 8.4; o roteiro manual permanece para reprodução e testes adicionais.
- Gestão de pagamentos registra recebimentos administrativos: sem gateway, estorno ou conciliação automática com XML.
- Sem promoções automáticas, juros, limite de usos de cupons ou interface administrativa.
- Categorias têm cadastro e consulta; nesta etapa não têm alteração/exclusão.
- Cada quarto representa uma unidade física; o estoque da categoria depende dos quartos vinculados.
- O scheduler nativo exige configuração no sistema operacional; o Docker já executa `schedule:work`.

Documentação: `README.md`, `GUIA_DOCKER.md` e `TESTES_MANUAIS.md`.
