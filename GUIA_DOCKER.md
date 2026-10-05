# Laravel e MySQL com Docker

## Validação de instalação limpa

Em 05/10/2026 foi instalada uma cópia do commit `09642c2` obtida por `git archive`, sem vendor, arquivos `.env` locais ou bancos existentes. `docker/init.ps1` gerou novas credenciais. A instalação usou o projeto Compose separado `foco-clean-validation`, rede e volumes novos, aplicação na porta 18080 e MySQL sem porta publicada. Apenas essas opções de isolamento foram alteradas em um arquivo Compose adicional; as etapas de build, setup e execução seguiram o fluxo abaixo.

Passaram 24 verificações funcionais: migrations e contagens de importação (3 hotéis, 6 quartos, 6 reservas, 6 hóspedes, 18 diárias, 1 pagamento), 9 categorias padrão e ausência de usuário padrão, acesso ao Swagger/OpenAPI, proteção sem token, login e permissões, vínculo quarto/categoria, cupom percentual, reserva com alocação automática e taxa, esgotamento de estoque, quitação, reenvio idempotente, logout e token revogado. `schedule:list` confirmou o registro de `hotels:import`, e o serviço scheduler iniciou normalmente. Esses checks de instalação complementam as suítes PHPUnit; não alteram a contagem de testes dessas suítes.

O ambiente temporário, seus volumes e as credenciais de validação foram removidos ao terminar. A aplicação normal na porta 8080 e o MySQL existente não foram recriados nem usados nesses testes. Ao repetir a instalação, use checkout e banco novos, evitando apontar os testes para dados reais. Com a instalação normal, os endereços continuam 8080 para a aplicação e 3307 para o Workbench.

Ambiente local com PHP 8.2/Apache, MySQL 8.4, migrations/importação inicial, categorias padrão e scheduler. No Windows, requer Docker Desktop ativo com backend Linux/WSL2; no Linux, Docker Engine em execução. Ambos utilizam Docker Compose com suporte a perfis; o perfil de integração também utiliza `tmpfs`. Build, migrations, importação e criação das categorias conferidos até 05/10/2026. A suíte PHPUnit executa em SQLite em memória; o perfil de integração verifica cenários de concorrência de bloqueios no MySQL.

## Iniciar no Linux

Com Git, Docker/Compose e OpenSSL disponíveis, na raiz do checkout:

```bash
sh docker/init.sh
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps -a
docker compose --env-file .env.docker logs setup
docker compose --env-file .env.docker exec --user www-data app php artisan users:create
docker compose --env-file .env.docker exec --user www-data app php artisan users:hotel SEU_EMAIL 1 manager
```

O script usa OpenSSL para gerar chave e senhas e cria `.env.docker` com permissão `600`; preserva um arquivo existente. Substitua `SEU_EMAIL` pelo email criado. Abra `http://127.0.0.1:8080/docs`, faça login e autorize com o token. Os comandos Compose de operação e testes abaixo também funcionam no terminal Linux. Não precisa de `php artisan serve` nem de CRON no host: app e scheduler já executam nos containers. Se o usuário não tiver acesso ao daemon Docker, use a configuração de acesso definida pelo administrador do computador.

Para parar preservando o banco, use `docker compose --env-file .env.docker down`. Para iniciar novamente, use `docker compose --env-file .env.docker up -d`. Após alterações de código, acrescente `--build`. A instalação sem Docker está descrita em [README.md](README.md).

## Iniciar no PowerShell

Validação concorrente separada: `docker compose --env-file .env.docker --profile integration run --build --rm integration-tests`. Usa MySQL temporário `mysql-concurrency`, banco `foco_concurrency`, sem porta publicada nem volume do banco da aplicação. Ao terminar, limpe apenas esse serviço com `docker compose --env-file .env.docker --profile integration rm --stop --force mysql-concurrency`. A configuração PHPUnit normal continua independente, usando SQLite em memória.

Na raiz do projeto:

```powershell
cd C:\PHP\Teste_Foco
powershell -ExecutionPolicy Bypass -File .\docker\init.ps1
docker compose --env-file .env.docker config --quiet
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps -a
docker compose --env-file .env.docker logs setup
```

O script cria `.env.docker` com chave e senhas aleatórias e preserva o arquivo se já existir. MySQL usa volume persistente e publica a porta 3307 somente no localhost para o Workbench. A aplicação fica em `http://127.0.0.1:8080/docs`, mantendo o Laravel nativo na porta 8000 disponível. Este banco MySQL é novo e independente do SQLite: usuários, tokens e reservas manuais anteriores não são copiados. O serviço setup espera o banco ficar saudável, aplica migrations e importa os XMLs corrigidos; app e scheduler só iniciam se setup terminar com sucesso. Setup pode executar novamente ao recriar o ambiente; a importação é idempotente.

Crie usuário e vínculo no novo banco:

```powershell
docker compose --env-file .env.docker exec --user www-data app php artisan users:create
docker compose --env-file .env.docker exec --user www-data app php artisan users:hotel SEU_EMAIL 1 manager
```

Faça login no Swagger da porta 8080 e informe o token em Authorize. Assets do Swagger ainda exigem internet. A imagem contém o código e dependências; após editar fontes, rode novamente `up -d --build`. Não precisa instalar PHP, Composer ou MySQL no computador para usar este ambiente.

## MySQL Workbench

MySQL também publica `127.0.0.1:3307` para acesso local pelo Workbench. Configure conexão Standard (TCP/IP), hostname `127.0.0.1`, port `3307`, username `foco` e default schema `foco`. A senha é o valor de `DB_PASSWORD` em `.env.docker`, sem o nome da variável. Teste em **Test Connection**, salve e abra **Schemas → foco → Tables**. Clique com botão direito na tabela e escolha **Select Rows - Limit 1000** para ver registros. A senha de login da API é independente da senha MySQL. A porta está limitada ao computador local.

## Comandos de operação

```powershell
docker compose --env-file .env.docker exec --user www-data app php artisan hotels:import
docker compose --env-file .env.docker exec --user www-data app php artisan schedule:list
docker compose --env-file .env.docker logs --tail=100 app scheduler setup
docker compose --env-file .env.docker down
```

`down` encerra containers mantendo os volumes. Não acrescente `-v` se quiser preservar dados: essa opção apaga os volumes do banco e logs. Preserve `.env.docker`: mudar as senhas nele não muda automaticamente as credenciais de um volume MySQL já inicializado. A importação agendada roda a cada hora pelo serviço scheduler, sem configurar o Agendador do Windows para este ambiente. Logs da aplicação ficam no volume storage; consulte com `docker compose --env-file .env.docker exec app tail -n 50 storage/logs/laravel.log`.

## PHPUnit no Docker

Execute `docker compose --env-file .env.docker --profile test run --build --rm tests`. A imagem de testes inclui PHPUnit e outras dependências de desenvolvimento. O banco é SQLite em memória, sem volumes nem conexão ao MySQL da aplicação. Não é necessário parar os containers. Concorrência MySQL é validada pela configuração separada do perfil integration, descrita acima.

## Validação manual do ambiente

- [ ] `config --quiet` não apresenta erros; build termina sem falhas.
- [ ] `ps -a`: mysql saudável, setup encerrado com código 0, app e scheduler em execução.
- [ ] Swagger porta 8080 abre, login funciona e hotéis respeitam vínculos.
- [ ] CRUD e reservas preservam os resultados do roteiro manual, agora em MySQL.
- [ ] Duas requisições simultâneas para mesmo quarto/período: uma 201, outra 409. Use duas janelas do Insomnia com mesmo JSON e período livre.
- [ ] Após `down` e `up -d`, usuários e reservas manuais continuam salvos.
- [ ] Confirme a execução horária pelos logs `storage/logs/import.log` após o horário previsto.

Se falhar, consulte `logs setup mysql`. Porta ocupada: altere HTTP_PORT em `.env.docker`. Docker indisponível: abra Docker Desktop e espere o engine iniciar. Este Compose é um ambiente de desenvolvimento/demonstração, ligado apenas ao localhost.
