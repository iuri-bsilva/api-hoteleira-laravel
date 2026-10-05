<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Hoteleira Foco — Swagger</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.32.0/swagger-ui.css">
    <style>
        body { margin: 0; background: #fafafa; }
        header { padding: 16px 24px; font: 16px system-ui; background: #172033; color: white; }
        header a { color: #a5d6ff; }
        .swagger-ui .info .description { max-width: 960px; }
        .swagger-ui .info .description h3 { margin-top: 24px; font-size: 18px; }
        .swagger-ui .info .description p,
        .swagger-ui .info .description li { line-height: 1.6; }
        .swagger-ui .info .description table { width: 100%; margin: 12px 0; }
        .swagger-ui .info .description th,
        .swagger-ui .info .description td { padding: 10px 12px; border-bottom: 1px solid #ddd; text-align: left; }
    </style>
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
                docExpansion: 'none',
                filter: true,
                tagsSorter: function (a, b) {
                    const order = ['Autenticação', 'Hotéis', 'Categorias', 'Quartos', 'Cupons', 'Reservas', 'Pagamentos'];
                    return order.indexOf(a) - order.indexOf(b);
                },
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
