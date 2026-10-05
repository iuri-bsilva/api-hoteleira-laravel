# Roteiro de testes manuais

## Regressão da listagem de hotéis e revisão final da arquitetura

Atualize a imagem Docker com `docker compose --env-file .env.docker up -d --build`. Não há migration nova.

1. GET `/api/hotels` sem token: 401 JSON. Com usuário sem vínculos: 200 e `data` vazio.
2. Com viewer ou manager vinculado a um hotel: 200, somente os hotéis vinculados, mantendo os dados do pivot e `per_page: 20`. Hotéis sem vínculo não podem aparecer.
3. Em banco de teste com mais de 20 vínculos, confira a primeira e segunda páginas por `?page=2`, sem duplicar ou expor hotéis de outro usuário.
4. Repita os roteiros de login, quartos, categorias, cupons, reservas e pagamentos deste documento. As rotas, JSONs e códigos HTTP devem continuar iguais aos anteriores à refatoração.

Para acompanhar uma requisição no código, siga `routes/api.php → Controller → interface do Service → Service → interface do Repository → Repository → Model/banco`. O Laravel resolve as interfaces pelos bindings do `AppServiceProvider`; Requests fazem a validação antes do método do controller. Na importação XML, o comando chama a interface do serviço de reservas e utiliza a validação compartilhada sem depender de uma requisição HTTP.

## Regressão da separação Request / Service / Repository de autenticação

Atualize o Docker com `docker compose --env-file .env.docker up -d --build`. Não há migration nova. Use um usuário de teste e mantenha os tokens apenas no Insomnia/Swagger, sem incluí-los na documentação ou em commits.

1. POST `/api/auth/login` com `{"email":"SEU_EMAIL","password":"SUA_SENHA","device_name":"Insomnia A"}`: 200, com `token_type`, `access_token`, `expires_at` oito horas após emissão e `user` sem senha. Email em maiúsculas também deve funcionar para o usuário cadastrado em minúsculas.
2. Email desconhecido ou senha incorreta: 401 com `Credenciais inválidas.`, sem token. Email inválido, senha ausente ou nome de dispositivo acima de 100 caracteres: 422.
3. GET `/api/auth/me` usando o token A: 200 com usuário, hotéis vinculados e perfis. Crie outro token pelo login com `device_name: Insomnia B`.
4. POST `/api/auth/logout` com o token A: 200. GET `/api/auth/me` com A passa a retornar 401; com B continua 200. O logout não revoga todas as sessões do usuário.
5. Cinco tentativas de login para o mesmo email/IP em um minuto são permitidas; a próxima retorna 429 com `Retry-After`. Aguarde o prazo para continuar. Tokens expirados devem retornar 401 (a suíte automatizada cobre esse caso sem esperar oito horas).

O Request valida os campos, o service verifica a senha e define a validade, o repository consulta/cria/revoga tokens e o controller monta as respostas. O middleware Sanctum e os limitadores continuam protegendo as rotas.

## Regressão da separação Request / Service / Repository de reservas

Reconstrua a imagem Docker antes de testar: `docker compose --env-file .env.docker up -d --build`. Use token manager e uma unidade de teste disponível; não há migration nova.

1. POST `/api/reservations` com quarto, hóspedes e uma diária por noite: 201, retornando quarto/hotel, hóspedes, diárias e pagamentos. GET da reserva e listagem mantêm detalhes e filtro pelos hotéis vinculados.
2. Informe `subtotal` e `total` incorretos no corpo: devem ser ignorados. Com diárias somando 250.00, desconto 30.00 e taxa 10.00, o total é 230.00. Cupom com desconto manual, diária faltante/duplicada e pagamento acima do total retornam 422 sem dados parciais.
3. Envie `check_in` ou `check_out` como array em vez de string: 422 JSON. Datas iguais/invertidas, período acima de 366 noites e IDs inválidos também devem ser rejeitados.
4. Repita a reserva no mesmo quarto/período: 409. Reserva adjacente, iniciando no check-out anterior, continua permitida. Reserva por categoria deve escolher a unidade livre de menor ID; categoria esgotada retorna 409.
5. Viewer não cria reservas; usuário sem acesso ao hotel não consulta o registro nem cria para seus quartos. Listagem deve mostrar apenas os hotéis vinculados.
6. Em ambiente descartável, importe os XMLs corrigidos duas vezes: IDs e contagens permanecem estáveis. Registre pagamento com chave em uma reserva importada e reimporte: pagamento da API permanece. XML original inválido ou soma de pagamentos acima do total deve reverter o lote inteiro. Use os passos detalhados das seções de importação, sem alterar dados em uso.
7. Execute o perfil integration: duas reservas concorrentes para a última unidade não podem ser aprovadas juntas; reservas por categoria com duas unidades devem alocar quartos distintos. Pagamentos concorrentes e reenvios idempotentes continuam protegidos.

O controller delega à interface do serviço. O serviço coordena regras e transações; o repository consulta/persiste. `ReservationDataValidator` mantém uma fonte de regras para API e importação XML, e o comando XML continua validando o lote completo.

## Regressão da separação Request / Service / Repository de categorias

Atualize a imagem Docker com `docker compose --env-file .env.docker up -d --build`. Use hotel e quartos de teste e substitua `HOTEL` pelo ID interno. Não há migration nova.

1. Com manager, POST `/api/hotels/HOTEL/categories` com `{"name":"Categoria de regressão"}`: 201. Mesmo nome no mesmo hotel: 422 em `name`. Nome igual em outro hotel com acesso manager continua permitido.
2. GET na mesma rota: 200, categorias ordenadas por ID e paginadas em 20, com `rooms_count`. Categoria recém-criada tem zero quartos.
3. Vincule dois quartos físicos à categoria por PATCH `/api/rooms/ID`. GET `/api/hotels/HOTEL/categories/availability?check_in=2029-01-10&check_out=2029-01-12`: `rooms_count` igual a 2 e `available_count` igual a 2 se ambos estiverem livres.
4. Reserve o primeiro quarto somente de 10 a 11 e o segundo somente de 11 a 12. Consulta de 10 a 12 deve retornar `available_count: 0`: não há uma unidade livre para as duas noites. Categorias vazias também devem aparecer com zero. Consulta de 12 a 13 retorna as duas unidades livres se não houver outras reservas.
5. Datas ausentes, inválidas, saída igual/anterior à entrada, arrays (`check_in[]=2029-01-10`) ou período acima de 366 noites: 422 JSON. Links da paginação preservam os filtros.
6. Viewer consulta categorias e disponibilidade, mas POST retorna 403. Usuário sem vínculo ao hotel: 403. Sem token: 401.

No código, Requests validam entrada, service verifica acesso e trata duplicidade, repository consulta/persiste, e controller monta a resposta. A criação da reserva por categoria continua sendo responsabilidade do serviço de reservas, com suas transações e bloqueios existentes.

## Regressão da separação Request / Service / Repository de cupons

No Docker, reconstrua a imagem com `docker compose --env-file .env.docker up -d --build`. Use token manager do hotel e substitua `HOTEL` e `CUPOM` pelos IDs internos. Não há migration nova.

1. POST `/api/hotels/HOTEL/coupons` com `{"code":" teste-arquitetura ","type":"percentage","amount":"10.00"}`: 201, código `TESTE-ARQUITETURA`. Guarde o ID. GET na mesma rota: 200, paginação de 20 e cupons somente desse hotel.
2. Repita o cadastro com o mesmo código: 422 no campo `code`, sem duplicação. Percentual `100.01`, valor com três casas, tipo desconhecido ou validade final anterior à inicial também retorna 422.
3. PATCH `/api/hotels/HOTEL/coupons/CUPOM` com `{"active":false,"amount":"99.00","code":"ALTERADO","type":"fixed"}`: 200, apenas `active` muda. Confira por GET que código, valor e tipo permanecem os originais. PATCH sem `active` retorna 422.
4. Reative com `{"active":true}`: 200. Crie reserva de teste com esse código e subtotal 250.00: desconto 25.00; com taxa 10.00, total 235.00. Desative o cupom: reserva existente mantém o total, nova reserva com ele retorna 422.
5. Viewer não pode listar, cadastrar ou alterar cupons: 403. Sem token: 401. Cupom existente na rota de outro hotel: 404, mesmo antes de validar o corpo. Hotel sem vínculo: 403 nas operações de gestão.

No código, o controller não contém validações, consultas ou transações; Requests cuidam dos campos, `CouponService` das regras e `CouponRepository` da persistência. O desconto da reserva continua sendo calculado pelo serviço de reservas.

## Regressão da separação Request / Service / Repository de quartos

No Docker, reconstrua a imagem antes de testar: `docker compose --env-file .env.docker up -d --build`. Use hotéis, categorias e quartos de teste; não há migration nova.

1. Com token manager do hotel 1, POST `/api/rooms` com `{"hotel_id":1,"name":"Quarto de regressão"}`: 201. Guarde o ID retornado. GET `/api/rooms/ID`: 200 com hotel e categoria. Listagem deve conter apenas hotéis vinculados e manter paginação de 20.
2. PATCH com `{"name":"Quarto atualizado"}`: 200. Vincule uma categoria do mesmo hotel por `room_category_id`: 200. Categoria de outro hotel: 422, mantendo o vínculo anterior. Envie `{"room_category_id":null}` para remover o vínculo.
3. Transfira um quarto sem reservas: sem manager no destino, 403; com manager nos dois hotéis, 200 se a categoria estiver removida ou pertencer ao destino. Uma categoria antiga mantida na transferência deve gerar 422, sem alterar o hotel.
4. Em quarto com reserva, tentativa de transferência para outro hotel acessível: 409. DELETE: 409. Quarto sem reservas pode ser excluído: 200 com `message`, seguido de GET 404.
5. GET `/api/rooms/availability?check_in=2029-01-10&check_out=2029-01-12&hotel_id=1`: 200, somente unidades livres para todo o período. Confira que reservas terminando na entrada ou começando na saída não bloqueiam. Links de paginação mantêm os filtros.
6. Saída igual/anterior à entrada, datas inválidas, período acima de 366 noites ou `check_in[]=2029-01-10`: 422 JSON, sem erro 500. Período de exatamente 366 noites continua permitido.
7. Viewer pode consultar, mas não cadastrar, alterar ou excluir. Hotel sem vínculo retorna 403 no acesso direto; sem token, 401.

No código, `RoomController` apenas coordena chamadas e respostas. As regras de transferência/categoria ficam no `RoomService`, as consultas no `RoomRepository` e os campos aceitos nos Requests. A atualização/exclusão mantém bloqueio e transação no service.

## Regressão da separação Request / Service / Repository de pagamentos

Use uma reserva de teste com saldo conhecido e token manager do hotel. Substitua `ID` pelo ID interno da reserva. A refatoração não exige migrations ou alteração dos JSONs. No Docker, atualize a imagem com `docker compose --env-file .env.docker up -d --build` antes de repetir estes passos.

1. GET `/api/reservations/ID/payments`: 200 com `total`, `paid`, `balance`, `status` e `payments` consistentes com os registros existentes.
2. POST na mesma rota com `{"method":1,"value":"0.01","idempotency_key":"123e4567-e89b-42d3-a456-426614174abc"}`: 201, se houver saldo e a chave ainda não tiver sido usada. O saldo deve diminuir 0.01.
3. Repita o mesmo corpo, inclusive usando a UUID em maiúsculas: 200, mesmo `payment.id`, sem novo recebimento.
4. Mantenha a chave e mude `value` para `0.02`: 409, sem alteração do saldo.
5. Use uma nova UUID e valor acima do saldo: 422 no campo `value`, sem gravação. UUID inválida ou valor com três casas decimais também retorna 422.
6. Repita com token viewer: GET 200, POST 403. Sem token: 401. Reserva de hotel sem vínculo: 403. ID inexistente: 404.
7. Para concorrência, repita o perfil integration descrito abaixo: duas operações acima do saldo não podem ser aprovadas juntas; reenvios concorrentes da mesma chave devem gerar apenas um pagamento.

Ao revisar o código, confirme que o controller não contém queries, cálculo financeiro ou `DB::transaction`; esses passos ficam no service/repository. As transações e os bloqueios devem continuar abrangendo leitura, verificação e gravação juntos.

## Instalação em ambiente novo

Validação realizada em 05/10/2026: 24 verificações funcionais passaram em cópia limpa do commit `09642c2`, sem usar o banco existente. Para repetir:

1. Use checkout e banco separados. Execute `docker/init.ps1` para gerar credenciais novas e inicie o Docker conforme `GUIA_DOCKER.md`. Caso a aplicação atual esteja ativa, utilize portas, projeto Compose e volumes diferentes; não compartilhe os volumes existentes.
2. Confira `logs setup`: migrations, importação e seeder sem erros. Banco inicial: 3 hotéis, 6 quartos, 6 reservas, 6 hóspedes, 18 diárias, 1 pagamento e 9 categorias. Deve começar sem usuário padrão.
3. Abra `/docs` e `/openapi.json`. GET `/api/hotels` sem token deve retornar 401.
4. Crie usuário e conceda acesso manager ao hotel 1 com os comandos do guia. Faça login, consulte `/auth/me` e confira hotéis vinculados.
5. Vincule um quarto a uma categoria, crie cupom percentual de 10% e reserve em período livre com diária 250.00 e taxa 10.00. Esperado total 235.00; repetir reserva na categoria com estoque esgotado retorna 409.
6. Registre pagamento 235.00: saldo zero. Repita a chave e os dados: 200, sem duplicação. Faça logout e confirme que o token passa a retornar 401.
7. Execute `php artisan schedule:list` dentro do container: `hotels:import` deve estar registrado. Confira que o scheduler está ativo.
8. Remova apenas o ambiente de validação e seus volumes descartáveis, identificando explicitamente seu projeto Compose. Nunca remova os volumes da aplicação em uso.

## Reprodução da validação concorrente automatizada

Em 05/10/2026 passaram 5 testes MySQL com 34 verificações, além dos 79 testes SQLite com 487 verificações. Os passos manuais abaixo continuam disponíveis; não são necessários para disparar a suíte automatizada.

```powershell
docker compose --env-file .env.docker --profile integration run --build --rm integration-tests
docker compose --env-file .env.docker --profile integration rm --stop --force mysql-concurrency
```

A suíte usa banco separado `foco_concurrency`, sem porta publicada e sem volume persistente. Cobre duas reservas diretas disputando quarto, categoria contra reserva direta, duas reservas por categoria com duas unidades, pagamentos concorrentes limitados ao saldo e duas tentativas com a mesma chave. As requisições são executadas pelo kernel HTTP em processos PHP separados. A limpeza final remove somente o MySQL temporário; não use `down -v`.

## Categorias e estoque por período

- [ ] Após preparar o Docker, GET `/hotels/1/categories` inclui Standard, Luxo e Suíte. Executar `php artisan db:seed --class=RoomCategorySeeder --force` novamente não duplica categorias nem altera vínculos existentes.

Base Docker `http://127.0.0.1:8080/api`, token manager do hotel 1. Use nome e período novos para repetir o roteiro sem conflitos. Anote IDs retornados.

1. POST `/hotels/1/categories` com `{"name":"Standard teste"}`: esperado 201. Anote ID como CATEGORIA. Repetir o nome no mesmo hotel: 422.
2. Crie dois quartos via POST `/rooms`, enviando `{"hotel_id":1,"name":"Standard 101 teste","room_category_id":CATEGORIA}` e depois outro nome para 102. Esperado 201 em ambos. Também é possível vincular quarto existente por PATCH `/rooms/ID` com `{"room_category_id":CATEGORIA}`.
3. GET `/hotels/1/categories`: rooms_count 2 na categoria criada. GET `/hotels/1/categories/availability?check_in=2028-05-10&check_out=2028-05-11`: available_count 2, se ambos livres.
4. POST `/reservations` com o JSON abaixo, substituindo CATEGORIA pelo número real:

```json
{
  "room_category_id": 1,
  "check_in": "2028-05-10",
  "check_out": "2028-05-11",
  "guests": [{"name":"Ana","last_name":"Silva","phone":"5571999999999"}],
  "dailies": [{"date":"2028-05-10","value":"100.00"}]
}
```

5. Esperado 201 com room_id do primeiro quarto livre. Repita a consulta: available_count 1. Repita o POST: 201 com outro room_id. Agora available_count 0; terceira reserva no período retorna 409, sem novos registros.
6. Consulte período começando na saída das reservas, 2028-05-11 a 2028-05-12: ambos podem estar livres (desde que sem outras reservas). Período parcialmente sobreposto continua excluindo as unidades ocupadas.
7. room_id e room_category_id juntos, nenhum dos dois ou categoria inexistente: 422. Categoria existente de hotel sem vínculo: 403. Categoria vazia: 409 ao reservar.
8. Criar/vincular quarto com categoria de outro hotel: 422 em room_category_id. PATCH com null remove o vínculo e diminui rooms_count. A reserva física existente continua ocupando o quarto.
9. Viewer lista categorias e consulta disponibilidade, mas não cadastra categoria/quarto nem reserva. Usuário sem vínculo recebe 403; sem token, 401. Datas ausentes/inválidas, saída igual/anterior ou mais de 366 noites: 422.
10. Em categoria com uma unidade, execute dois POST simultâneos com mesmo período em duas janelas do Insomnia: esperado um 201 e um 409. Categoria com duas unidades: dois 201 em quartos diferentes. Teste também reserva direta por room_id concorrendo com reserva por categoria; nunca deve haver sobreposição no mesmo quarto.
11. Reimportação XML no mesmo hotel preserva categoria atribuída a quarto importado. Não altera nem duplica o estoque cadastrado pela API.

Cobertura PHPUnit: `app/tests/Feature/RoomCategoryTest.php` e teste de preservação no `XmlImportTest.php`. Concorrência de bloqueios também é coberta pela suíte MySQL separada; os passos manuais permitem reproduzi-la.

## Pagamentos após criação da reserva

Base Docker: `http://127.0.0.1:8080/api`. No Swagger, use **Pagamentos** e um token manager do hotel. Use IDs retornados pela API. Prepare uma reserva nova em quarto/período livre, para não misturar pagamentos anteriores.

POST `/reservations` (troque room_id/datas se necessário):

```json
{
  "room_id": 1,
  "check_in": "2028-02-10",
  "check_out": "2028-02-11",
  "discount": "30.00",
  "service_fee": "10.00",
  "guests": [{"name": "Ana", "last_name": "Silva", "phone": "5571999999999"}],
  "dailies": [{"date": "2028-02-10", "value": "250.00"}],
  "payments": [{"method": 1, "value": "100.00"}]
}
```

- [ ] 201, total 230.00. Anote o ID da reserva (`RESERVA`). GET `/reservations/RESERVA/payments`: 200, paid 100.00, balance 130.00, status partial.
- [ ] POST `/reservations/RESERVA/payments` com o JSON abaixo: 201, paid 130.00, balance 100.00, status partial; payment possui ID, recorded_at e chave.

```json
{
  "method": 1,
  "value": "30.00",
  "idempotency_key": "123e4567-e89b-42d3-a456-426614174000"
}
```

- [ ] Reenvie exatamente o JSON: 200, mesmo payment.id, saldo inalterado e nenhum registro duplicado.
- [ ] Com a mesma chave, altere value para 31.00 ou method para 2: 409, sem escrita.
- [ ] Envie value 100.01 com chave nova `123e4567-e89b-42d3-a456-426614174001`: 422 em value, pois supera o saldo.
- [ ] Com essa chave nova, envie value 100.00: 201, paid 230.00, balance 0.00, status paid. Tente mais 0.01 com outra chave: 422.
- [ ] GET `/reservations/RESERVA` também mostra os três pagamentos e mantém total 230.00. Confira no Workbench.
- [ ] Valor zero/negativo, três casas decimais, chave ausente/inválida e método fora de 1..65535: 422. Cada recebimento novo precisa de uma UUID diferente.
- [ ] Viewer pode consultar e recebe 403 ao registrar. Usuário sem vínculo recebe 403 nas duas rotas; sem token, 401; reserva inexistente, 404.
- [ ] Reserva sem pagamentos retorna status unpaid; total zero retorna paid e balance 0.00.
- [ ] Concorrência MySQL: em reserva de total 100.00 sem pagamentos, envie simultaneamente dois pagamentos de 80.00 com chaves diferentes em duas janelas do Insomnia. Esperado: um 201 e outro 422, paid 80.00. Repetir simultaneamente a mesma chave deve produzir um registro (201 e 200).
- [ ] Importação (use ambiente separado): registre pagamento adicional em reserva importada com saldo e execute hotels:import duas vezes. ID/chave do pagamento adicional devem permanecer, sem duplicação. Se dados XML mais pagamentos da nova rota excederem o total, a importação deve falhar e reverter todo o lote. Não altere os XMLs originais para esse teste.

Cobertura automatizada: `app/tests/Feature/PaymentTest.php`. A suíte funcional usa SQLite em memória; simultaneidade também foi validada na suíte MySQL separada descrita no início deste roteiro.

## Cupons

### Cupons percentuais

No Swagger, POST `/hotels/1/coupons`, com token manager:

```json
{"code":"FOCO10P","type":"percentage","amount":"10.00","minimum_subtotal":"200.00","active":true}
```

Esperado 201, type percentage. Crie reserva em quarto/período livre:

```json
{
  "room_id": 1,
  "check_in": "2028-04-10",
  "check_out": "2028-04-11",
  "coupon_code": "FOCO10P",
  "service_fee": "10.00",
  "guests": [{"name":"Ana","last_name":"Silva","phone":"5571999999999"}],
  "dailies": [{"date":"2028-04-10","value":"250.00"}]
}
```

- [ ] 201, subtotal 250.00, discount 25.00, service_fee 10.00 e total 235.00. Percentual incide somente sobre diárias.
- [ ] Novo cupom percentage com amount 100.01, zero, negativo ou três casas: 422 em amount; type inválido: 422 em type.
- [ ] Cadastro sem type continua fixed, preservando cupons existentes e o roteiro anterior.
- [ ] Arredondamento: crie cupom percentage amount 50.00, sem mínimo. Em reserva nova com diária 0.01 e sem taxa, desconto 0.01, total 0.00 (meio centavo arredonda para cima).
- [ ] Cupom percentage amount 100.00 com diária 250.00 e taxa 10.00: desconto 250.00, total 10.00. A taxa não recebe desconto.
- [ ] Validade, mínimo, hotel, permissões, desativação e proibição de discount junto continuam iguais às regras de cupom fixo.
- [ ] Desativar cupom após reserva não altera o desconto salvo. Pagamentos não podem superar o novo total com desconto.

Cobertura PHPUnit: `app/tests/Feature/PercentageCouponTest.php`, incluindo centavos, percentuais fracionados, 100%, menor percentual e limite monetário.

- [ ] Manager no hotel 1: POST `/hotels/1/coupons` com code FOCO30, amount 30.00 e minimum_subtotal 200.00: 201. Repetir o código no mesmo hotel: 422.
- [ ] Em período livre, reserva com diárias 250.00, coupon_code FOCO30 e service_fee 10.00, sem discount: 201, desconto 30.00, total 230.00.
- [ ] Cupom de outro hotel, inexistente, inativo, vencido, ainda não válido ou subtotal abaixo do mínimo: 422 sem reserva parcial.
- [ ] Cupom e discount juntos (inclusive zero): 422.
- [ ] PATCH cupom active false: reservas anteriores mantêm desconto e total; novas aplicações são rejeitadas.
- [ ] Viewer não lista, cria ou altera cupons: 403. Manager sem vínculo no hotel também não tem acesso.

## Desconto e taxa

- [ ] Em período livre, envie reserva com diárias somando 250.00, discount 30.00 e service_fee 10.00: 201, subtotal 250.00 e total 230.00. Confirme no GET e no Workbench.
- [ ] Sem ajustes: o total continua igual às diárias; discount e service_fee retornam 0.00.
- [ ] Desconto maior que subtotal, ajuste negativo ou três casas decimais: 422, sem persistir reserva.
- [ ] Pagamento maior que o total após desconto: 422.
- [ ] Desconto integral e payments vazio em período livre: 201, total 0.00.
- [ ] Reservas antigas mantêm total e ganham subtotal igual ao valor anterior, com ajustes zero.

## Logs de operações

- [ ] GET `/api/hotels` autenticado: 200 e header X-Request-ID. Localize o mesmo ID no audit log, com user_id, rota, status e duração.
- [ ] GET `/api/hotels` sem token: 401 e registro warning com user_id null.
- [ ] POST rooms autorizado: 201; PATCH e DELETE no quarto criado: 200; cada operação gera seu próprio registro.
- [ ] Envio inválido: 422; acesso a hotel sem vínculo: 403; reserva sobreposta: 409; registro inexistente: 404. Cada erro deve ter header e log warning.
- [ ] Login errado: 401. Confirme que senha/email/body não aparecem no canal audit. Login correto não deve registrar o token retornado.
- [ ] Confira que dados dos hóspedes, headers e query strings não aparecem nos registros audit.

## Autenticação (executar antes dos testes da API)

Antes dos testes anteriores, conceda manager no hotel usado: `php artisan users:hotel SEU_EMAIL 1 manager`. Para operar hotel 2 ou 3, conceda acesso separadamente.

## Permissões por hotel

- [ ] Sem vínculos, GET hotels/rooms/reservations: 200 e data vazio. GET rooms/1: 403.
- [ ] `php artisan users:hotel SEU_EMAIL 1 viewer`: listagens mostram somente hotel 1 e seus quartos/reservas; GET rooms/1: 200; GET rooms/3 (hotel 2): 403.
- [ ] Como viewer, POST rooms no hotel 1, PATCH/DELETE rooms/1 e POST reservations com room_id 1: 403, sem alterações.
- [ ] Mude para manager no hotel 1: cadastro/alteração/exclusão de quarto de teste e criação de reserva em período livre funcionam. Regras de conflito continuam valendo.
- [ ] Como manager apenas do hotel 1, tente POST rooms no hotel 2: 403. Tente transferir um quarto novo sem reservas ao hotel 2: 403.
- [ ] Conceda manager também no hotel 2: transferência desse quarto sem reservas é aceita. Revogue hotel 2 e confirme que o quarto deixa de aparecer na listagem e acesso direto retorna 403.
- [ ] Revogue hotel 1 usando o mesmo token: acesso desaparece imediatamente. GET auth/me mostra vínculos atuais.
- [ ] Com dois usuários, confira que vínculos de um não concedem acesso ao outro. Ao final, restaure manager nos hotéis necessários aos demais testes.

- [ ] Crie usuário com `php artisan users:create`. Esperado: solicita senha oculta e confirmação; rejeita email duplicado e senha sem 12 caracteres, letras e números.
- [ ] GET `/api/hotels` sem token: 401 JSON.
- [ ] POST `/api/auth/login` com email e senha incorretos: 401, mensagem genérica.
- [ ] Login correto: 200, access_token, validade de oito horas e usuário sem password.
- [ ] Insomnia Auth → Bearer Token, ou Swagger Authorize: cole access_token. GET `/api/auth/me` e `/api/hotels`: 200.
- [ ] POST `/api/auth/logout`: 200. Repita GET com o mesmo token: 401. Faça novo login e confirme que funciona.
- [ ] Token inventado e token expirado: 401.
- [ ] Repita login seis vezes com mesmo email/IP em um minuto: 429. Aguarde Retry-After e tente novamente.
- [ ] Após autenticar, repita os fluxos de quarto/reserva; devem preservar os resultados anteriores.

Execute com Postman, Insomnia ou Thunder Client. Servidor: `php artisan serve`, dentro de `app`. Base `http://127.0.0.1:8000/api`. Headers: `Accept: application/json`, `Content-Type: application/json`. Anote o resultado real, data e evidência de cada caso. As caixas abaixo estão pendentes para você executar.

## Importação

- [ ] Execute `php artisan hotels:import`. Esperado: código de saída 0, 3 hotéis, 6 quartos, 6 reservas, 6 hóspedes, 18 diárias e 1 pagamento em uma base inicialmente vazia.
- [ ] Repita o comando. Esperado: mesmas quantidades, sem duplicação. Compare `GET /hotels`, `/rooms` e `/reservations`.
- [ ] Execute `php artisan hotels:import --path="C:\PHP\Teste_Foco"`. Esperado: código 1 e erro da reserva 6. Confirme que a base permanece igual à anterior.
- [ ] Em uma pasta temporária, copie os três XMLs e altere nome de hotel e uma diária para uma data inválida. Importe com `--path`. Esperado: falha e nenhum nome alterado, comprovando rollback do lote.
- [ ] Em cópias temporárias, teste arquivo ausente, XML malformado, ID duplicado, hotel inexistente, quarto de outro hotel, total divergente e `DOCTYPE`. Esperado: rejeição com mensagem, sem persistência parcial.
- [ ] Execute `php artisan schedule:list`. Esperado: importação a cada hora. Configure o agendador conforme README e confirme o log após o horário previsto.

## Quartos

- [ ] `GET /hotels`: 200 e três hotéis. `GET /rooms`: 200, paginação e seis quartos importados.
- [ ] `POST /rooms` com `{"hotel_id":1,"name":"Suíte manual"}`: 201. Anote o `id` como QUARTO_TESTE.
- [ ] `GET /rooms/QUARTO_TESTE`: 200 e nome cadastrado.
- [ ] `PATCH /rooms/QUARTO_TESTE` com `{"name":"Suíte atualizada"}`: 200. Confirme por GET.
- [ ] `PUT /rooms/QUARTO_TESTE` com `{"hotel_id":2,"name":"Suíte hotel 2"}`: 200 enquanto não tiver reservas.
- [ ] POST com nome vazio, hotel inexistente e nome maior que 255 caracteres: 422.
- [ ] GET/PATCH/DELETE com ID inexistente: 404 JSON, mesmo sem header Accept.
- [ ] `DELETE /rooms/QUARTO_TESTE`: 200; GET posterior retorna 404.
- [ ] `DELETE /rooms/1`: 409 porque o quarto importado possui reserva.
- [ ] `PATCH /rooms/1` com `{"hotel_id":2}`: 409.

## Reservas

Use o JSON de reserva do README. Se já usou esse período, escolha outro período livre e ajuste também as datas das diárias.

- [ ] POST `/reservations`: 201, total `250.00`, hóspedes, duas diárias e um pagamento. Anote RESERVA_TESTE.
- [ ] GET `/reservations/RESERVA_TESTE`: 200; detalhes iguais ao POST. Confira também a listagem.
- [ ] Repita o POST: 409, sem nova reserva.
- [ ] No mesmo quarto, tente entrada `2027-01-11` e saída `2027-01-13`, com diárias correspondentes: 409 por sobreposição.
- [ ] Tente um período que englobe totalmente a reserva existente: 409.
- [ ] Tente entrada no dia `2027-01-12`, saída `2027-01-13`, diária apenas de `2027-01-12`: 201, pois saída e entrada podem coincidir.
- [ ] Mesmo período original em outro quarto livre: 201.
- [ ] Check-out igual ou anterior ao check-in, data inexistente e quarto inexistente: 422.
- [ ] Sem hóspedes, nome de hóspede ausente, telefone acima de 30 caracteres: 422.
- [ ] Diária negativa, com três casas decimais, duplicada, ausente ou fora do período: 422.
- [ ] Pagamento acima do total ou método zero: 422. Pagamento parcial e ausência de payments: aceitos em períodos livres.
- [ ] Envie campo `total` com `0` junto às diárias válidas em período livre: total deve continuar sendo calculado pelas diárias.
- [ ] Envie campos extras `id` e `external_id`: não podem alterar os identificadores gerados pelo servidor.
- [ ] Em MySQL, envie simultaneamente duas reservas para mesmo quarto/período em duas abas: uma 201 e outra 409. Faça essa verificação separadamente da base SQLite.

## Swagger

- [ ] Abra `http://127.0.0.1:8000/docs` com internet disponível. Esperado: grupos Hotéis, Quartos e Reservas, com dez operações.
- [ ] Em GET `/hotels`, clique **Try it out** e **Execute**. Esperado: 200 e lista paginada.
- [ ] Em POST `/rooms`, execute o exemplo. Esperado: 201; anote o ID. Consulte, altere e exclua esse quarto pela interface.
- [ ] Em POST `/reservations`, use o exemplo em um período livre. Esperado: 201 e total 250.00. Repetir o envio retorna 409.
- [ ] Abra `/openapi.json` ou importe esse endereço no Insomnia. Esperado: contrato OpenAPI 3.0.0 com todas as operações.

## Registro dos resultados da rodada

| Caso | Data | Resultado real | Evidência/observação |
|---|---|---|---|
| Importação | | | |
| Reimportação | | | |
| Rollback | | | |
| CRUD quartos | | | |
| Reservas e disponibilidade | | | |
| Validações | | | |
| Agendamento | | | |
| Concorrência MySQL | | | |
# Consulta de disponibilidade por período

No Docker, base `http://127.0.0.1:8080/api`. Use Bearer token de usuário vinculado ao hotel. No Swagger, operação **Quartos → availableRooms**. Estes testes não criam nem alteram dados; utilize um quarto com reserva conhecida, anotando entrada e saída.

1. GET `/rooms/availability?check_in=2027-10-10&check_out=2027-10-12&hotel_id=1`: esperado 200, lista em `data`, quantidade livre em `total`. Se a reserva FOCO30 do roteiro foi criada no quarto 1 nessas datas, esse quarto não deve aparecer. Adapte as datas se necessário.
2. Consulte um período parcialmente sobreposto, como 2027-10-09 a 2027-10-11: o quarto reservado também deve ser excluído.
3. Consulte 2027-10-09 a 2027-10-10 e 2027-10-12 a 2027-10-13: o quarto pode aparecer, desde que nenhuma outra reserva se sobreponha. A data de saída é exclusiva.
4. Remova `hotel_id`: apenas quartos de hotéis vinculados devem aparecer. Filtrar um hotel existente sem vínculo deve retornar 403. Viewer pode consultar; sem token, 401.
5. Omita datas, use data inválida, saída igual/anterior à entrada ou período maior que 366 noites: esperado 422 JSON. Hotel inexistente também retorna 422.
6. Em hotel com mais de 20 quartos livres, siga `next_page_url`: os filtros devem permanecer e a segunda página deve continuar contendo apenas quartos livres.
7. A consulta não bloqueia quartos. Se outro usuário reservar após a consulta, tentar criar reserva sobreposta deve continuar retornando 409.

Cobertura automatizada: `app/tests/Feature/AvailabilityTest.php`, usando SQLite em memória. Não representa estoque de categorias como “10 quartos Standard”; cada registro representa uma unidade física.
