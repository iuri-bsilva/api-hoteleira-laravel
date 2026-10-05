<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Hoteleira Foco — Swagger</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.32.0/swagger-ui.css">
    <style>body{margin:0;background:#fafafa}header{padding:16px 24px;font:16px system-ui;background:#172033;color:white}header a{color:#a5d6ff}</style>
</head>
<body>
    <header>Documentação da API Foco · <a href="{{ asset('openapi.json') }}">Baixar OpenAPI</a><br><small>“Try it out” executa operações reais no banco local. Use dados de teste.</small></header>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.32.0/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function () {
            if (typeof SwaggerUIBundle === 'undefined') {
                document.getElementById('swagger-ui').textContent = 'Não foi possível carregar o Swagger UI. Verifique sua conexão com a internet ou abra o arquivo OpenAPI no Insomnia.';
                return;
            }
            SwaggerUIBundle({
                url: @json(asset('openapi.json')),
                dom_id: '#swagger-ui',
                deepLinking: true,
                validatorUrl: null,
                presets: [SwaggerUIBundle.presets.apis],
                requestInterceptor: function (request) {
                    request.headers['Accept'] = 'application/json';
                    return request;
                }
            });
        };
    </script>
</body>
</html>
