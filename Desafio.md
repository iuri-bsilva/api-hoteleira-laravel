# Project Challenge

<br>
<br>
<br>
<br>
<p align="center" id="focomultimidia"><img src="https://focomultimidia.com/assets/imgs/logo/logo-foco.svg" width="150px"></p>
<center><a href="https://focomultimidia.com">Focomultimídia</a></center>
<br>
<br>
<br>
<br>

A Foco Multimídia é uma empresa com direcionamento a hotelaria. A solução da empresa consiste em um conjunto de sistemas que facilite a gestão a ponto de aumentar a produtividade dos funcionários, do hotel e do hospede.

O sistema da foco possui diversos módulos, como alguns listados abaixo:

- Módulos de pagamentos
- Gerência de quartos
- Integração com canais de venda
- Motor de reservas
- Plus

Determinados módulos possuem especifidades e tecnologias adequadas para cada caso. Por exemplo há APIs que disponibilizam dados no formado XML e outras no formato JSON.

Nesse contexto, é de interesse que o sistema possua diferentes módulos para o gerenciamento e facilidade dos clientes.

Abaixo esta listado o desafio relacionado a vida hoteleira.

<h1 id="summary">Sumário</h1>

<ul>
  <li><a href="#definition">Definições</a></li>
  <li><a href="#introduction">Introdução</a></li>
  <li><a href="#instructions">Instruções</a></li>
  <li><a href="#requirements">Requisitos</a></li>
  <li><a href="#differences">Diferenciais</a></li>
  <li><a href="#links">Links uteis</a></li>
  <li><a href="#reference">Referência</a></li>
</ul>

<h1 id="definition">Definições</h1>

- Check-in: Data de entrada no hotel
- Check-out: Data de Saida no hotel
- Daily: Diárias do hotel, há valores por dia.

<h1 id="introduction">Introdução</h1>

Muitos de produtos que fazem o uso de API/Webservice, utilizam mecanismos de comunicação que viabilizam a troca de dados entre produtos e/ou serviços diferentes. Por exemplo, há possibilidade de desenvolver um serviço de comunicação com um gateway de pagamentos para a utilização em um _e-commerce_ e por sua vez efetuar uma compra de cartão de crédito.

Alguns desses produtos utilizam em sua <a href="#api">API</a> (_Application Program Interface_) formatos de transferências de dados como <a href="#json">JSON</a> (_JavaScript Object Notation_) e <a href="#xml">XML</a> (_eXtensible Markup Language_) para troca de dados. Essas formatações facilitam a interpretação pelas linguagens de programação a manipular os dados de melhor forma para a persistência no banco de dados.

Abaixo estão listados exemplos dois exemplos de formatações de retorno de uma API fictícia que retorna os hotéis cadastrados em sua base de dados. Sendo o primeiro exemplo em XML e o segundo em JSON.

```XML
<?xml version="1.0" encoding="UTF-8"?>
<hotels>
  <hotel id="1" name="Focomultimidia Hotel">
</hotels>
```

```JSON
{
  "hotels":[
    {
      "id": 1,
      "name": "Focomultimitia Hotel"
    }
  ]
}
```

<br>

<h1 id="instructions">Instruções</h1>

- [ ] Documentar todo o processo.
- [ ] Realizar o desenvolvimento dos requisitos.
- [ ] Versionar modelagem de banco de dados e projeto.
- [ ] Respostas da API REST devem ser em JSON.

<h1 id="requirements">Requisitos</h1>

- [ ] Documentação explicando os processos do desenvolvimento como: como executar o cron, como cadastrar um quarto, etc...
- [ ] Realizar a modelagem do banco de dados utilizando como base os XMLs informados na pasta [_database_](database). A modelagem pode ser realizada por meio da ferramenta [Mysql Workbench](https://www.mysql.com/products/workbench/).
- [ ] Realizar o desenvolvimento de um script/comando em PHP, Laravel ou Lumen da importação do XML. Cujo objetivo é fazer a recuperação dos dados do XML e persistir no banco conforme a modelagem e dados. Onde será executado via CRON.
- [ ] Desenvolver por meio de API REST um CRUD de quartos / acomodações.
- [ ] Desenvolver por meio de API REST um POST de reserva.

<h1 id="differences">Diferenciais</h1>

 - [ ] Documentação utilizando swagger / OpenApi 3.0.0
 - [ ] Testes automatizados com PHPUnit
 - [ ] Padrões de projeto
 - [ ] Docker
 - [ ] GIT Pattern
 - [ ] HTTP Verbs
 - [ ] Segurança
 - [ ] Sistema
   - [ ] Considerar descontos para reserva como cupons, promoções, etc...
   - [ ] Considerar acréscimo para a reserva como Juros, Taxas de servico
   - [ ] Considerar disponibilidade para reserva. Ex: Quarto Standard tem 10 disponibilidades.
   - [ ] Gerencia do hoteleiro
     - Estabelecimento possui usuários e permissões
     - Gestão de pagamentos
   - [ ] Logs de aplicação

<h1 id="links">Links uteis</h1>

- https://www.php.net/manual/pt_BR/index.php
- https://laravel.com/
- https://lumen.laravel.com/docs/10.x
- https://swagger.io/
- https://phpunit.de/

<h1 id="reference">Referência</h1>

<ol>
  <li id="api">REDHAT. O que é uma API? 2021. Disponível em: https://www.redhat.com/pt-br/topics/api/what-are-application-programming-interfaces.</li>
  <li id="json">JSON. introdução ao JSON. 2021. Disponível em: http://www.json.org/json-pt.html.</li>
  <li id="xml">ALMEIDA, M. B. Uma introdução ao xml, sua utilização na internet e alguns conceitos complementares. Ciência da informação, SciELO Brasil, v. 31, p. 5–13, 2002.</li>
</ol>

<center>

## Considerem o prazo de 5 dias a partir do recebimento do desafio. Boa sorte e nos surpreenda!

![Boa sorte GIF](https://media2.giphy.com/media/l3BwSPbqx3QGKEgpp2/giphy.gif?cid=ecf05e47f5732v9in8lit8i3snduzf9axyuayg2kvtsczu2o&ep=v1_gifs_search&rid=giphy.gif&ct=g)

</center>
