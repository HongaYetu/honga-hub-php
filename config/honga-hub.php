<?php

return [
    'chave_servico' => env('HONGAHUB_CHAVE_SERVICO'),
    'jwt_segredo' => env('HONGAHUB_JWT_SEGREDO'),
    'api_url' => env('HONGAHUB_API_URL', 'https://hub.hongayetu.com'),
    'socket_url' => env('HONGAHUB_SOCKET_URL', 'https://hub.hongayetu.com'),
    'api_key' => env('HONGAHUB_API_KEY'),
    'conector_segredo' => env('HONGAHUB_CONECTOR_SEGREDO'),
    'token_ttl_segundos' => (int) env('HONGAHUB_TOKEN_TTL', 900),

    /*
     * Discord de administração, através do Honga Hub (o hub guarda o token do bot
     * e recebe os cliques). Desligado fora de produção: em dev liga-se de
     * propósito, e o hub marca essas mensagens com «🧪» e não lhes executa acções.
     */
    'discord' => [
        'activo' => (bool) env('HONGAHUB_DISCORD_ACTIVO', env('APP_ENV') === 'production'),
        'canal_forcado' => env('HONGAHUB_DISCORD_CANAL_FORCADO'),
        // O nome do serviço nos títulos do resumo diário.
        'nome_servico' => env('HONGAHUB_DISCORD_NOME', env('APP_NAME')),
        // Cada excepção reportada vai para o #erros (agrupada por ficheiro + linha).
        'erros' => (bool) env('HONGAHUB_DISCORD_ERROS', true),
        // Os botões: 'accao' => classe que implementa Discord\Accoes\AccaoDiscord.
        'accoes' => [],
        // Chaves de log que abrem um alerta no #criticos: 'chave' => 'o que quer dizer'.
        'logs_criticos' => [],
        // Pedaços do nome de um comando agendado que o tornam de dinheiro (alerta com menção).
        'tarefas_de_dinheiro' => ['cobrar', 'pagamento', 'payout', 'levantamento', 'factura', 'reconcil', 'renovar'],
        // As secções do resumo diário (Discord\Resumo\Seccao), por ordem. Vazio = sem resumo.
        'resumo' => [],
        // Hora do resumo diário, na timezone da app.
        'resumo_hora' => env('HONGAHUB_DISCORD_RESUMO_HORA', '23:00'),
    ],
];
