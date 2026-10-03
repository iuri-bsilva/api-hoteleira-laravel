# Roteiro de testes manuais

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

## Registro dos resultados

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
