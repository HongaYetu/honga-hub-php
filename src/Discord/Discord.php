<?php

namespace Hongayetu\HongaHub\Discord;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * A porta de um serviço para o Discord de administração, através do Honga Hub.
 *
 * O serviço nunca fala com o Discord: publica no hub (s2s, JWT com o
 * `jwt_segredo` do serviço), que guarda o token do bot, desenha a mensagem e
 * recebe os cliques. Tudo vai pela fila — um hub em baixo não pode partir o
 * pedido de quem estava a usar o serviço.
 *
 * Pedido de publicação: `canal` (pelo nome, sem emoji), `referencia` (a mesma
 * referência edita em vez de repetir), `titulo`, e opcionais `descricao`,
 * `nivel` (critico|erro|aviso|info|sucesso), `campos` [{nome, valor, linha}],
 * `ligacoes` [{rotulo, url}], `botoes` [{accao, rotulo, estilo, pede_motivo,
 * opcoes, dados}], `imagem`, `agrupar` + `incremento`, `mencionar`, `etiquetas`.
 */
class Discord
{
    public static function activo(): bool
    {
        return (bool) config('honga-hub.discord.activo');
    }

    public static function publicar(array $pedido): void
    {
        if (! self::activo()) {
            return;
        }

        EnviarAoHubJob::dispatch('publicar', self::comCanalForcado($pedido));
    }

    /** Mensagens que têm de aparecer por esta ordem: os workers correm jobs em paralelo, a cadeia não. */
    public static function publicarEmSequencia(array $pedidos): void
    {
        if (! self::activo() || $pedidos === []) {
            return;
        }

        Bus::chain(array_map(fn (array $p) => new EnviarAoHubJob('publicar', self::comCanalForcado($p)), array_values($pedidos)))->dispatch();
    }

    /** O caso foi resolvido fora do Discord (no painel): fecha também a mensagem. */
    public static function resolver(string $referencia, string $texto, bool $sucesso = true, ?string $etiqueta = null): void
    {
        if (! self::activo()) {
            return;
        }

        EnviarAoHubJob::dispatch('resolver', array_filter([
            'referencia' => $referencia,
            'texto' => mb_substr($texto, 0, 300),
            'sucesso' => $sucesso,
            'etiqueta' => $etiqueta,
        ], fn ($v) => $v !== null));
    }

    /** O envio em si — só o job o chama. */
    public static function enviarAgora(string $caminho, array $corpo): array
    {
        $corpo['ambiente'] ??= self::ambiente();

        if ($caminho === 'publicar' && $corpo['ambiente'] !== 'producao') {
            $corpo['origem'] ??= mb_substr((string) gethostname(), 0, 60);
        }

        $resposta = Http::withToken(self::tokenS2s())
            ->acceptJson()
            ->timeout(20)
            ->post(rtrim((string) config('honga-hub.api_url'), '/').'/v1/s2s/discord/'.$caminho, $corpo);

        if (! $resposta->successful()) {
            throw new RuntimeException("Hub respondeu {$resposta->status()} a discord/{$caminho}: ".mb_substr($resposta->body(), 0, 300));
        }

        return $resposta->json() ?? [];
    }

    /**
     * `producao`, `dev` (o `local` do Laravel) ou o nome do ambiente. O hub marca
     * tudo o que não é produção com «🧪» e não deixa essas mensagens executar
     * acções — o conector que ele conhece é o de produção.
     */
    public static function ambiente(): string
    {
        // Um serviço de produção com o APP_ENV errado (o Humbi esteve a `local`)
        // declara-o aqui, sem mexer no resto da aplicação.
        if ($declarado = config('honga-hub.discord.ambiente')) {
            return (string) $declarado;
        }

        return match ($env = (string) config('app.env')) {
            'production' => 'producao',
            'local' => 'dev',
            default => $env,
        };
    }

    private static function comCanalForcado(array $pedido): array
    {
        $forcado = config('honga-hub.discord.canal_forcado');

        return $forcado ? ['canal' => $forcado] + $pedido : $pedido;
    }

    private static function tokenS2s(): string
    {
        $segredo = (string) config('honga-hub.jwt_segredo');
        $servico = (string) config('honga-hub.chave_servico');

        if ($segredo === '' || $servico === '') {
            throw new RuntimeException('HONGAHUB_CHAVE_SERVICO e HONGAHUB_JWT_SEGREDO são precisos para publicar no Discord.');
        }

        $agora = time();

        return JWT::encode(['iss' => $servico, 's2s' => true, 'iat' => $agora, 'exp' => $agora + 60], $segredo, 'HS256');
    }
}
