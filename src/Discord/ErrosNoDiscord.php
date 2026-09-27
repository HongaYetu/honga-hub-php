<?php

namespace Hongayetu\HongaHub\Discord;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cada excepção reportada vai para o #erros, agrupada por ficheiro + linha (o
 * critério das famílias do Telescope). Um erro repetido é uma mensagem só, com o
 * número de vezes: as ocorrências de 15 s juntam-se num pedido, e o hub só edita
 * a mensagem de 15 em 15 s.
 *
 * Liga-se sozinho pelo `reportable` do handler (ver o service provider); não
 * precisa do Telescope. Com o Telescope instalado, a mensagem leva a ligação.
 */
class ErrosNoDiscord
{
    public const JANELA_S = 15;

    public static ?string $jobActual = null;

    public static function reportar(Throwable $e): void
    {
        if (! Discord::activo() || ! config('honga-hub.discord.erros', true)) {
            return;
        }

        try {
            // O próprio envio ao hub nunca volta ao Discord.
            if (str_contains($e->getTraceAsString(), 'EnviarAoHubJob') || str_contains($e->getFile(), 'honga-hub-php/src/Discord')) {
                return;
            }

            $familia = md5($e->getFile().$e->getLine());
            $contador = "hub-discord:erro:{$familia}:n";

            Cache::add($contador, 0, 3600);
            Cache::increment($contador);

            if (! Cache::add("hub-discord:erro:{$familia}:tranca", 1, self::JANELA_S)) {
                return;
            }

            EnviarAoHubJob::dispatch('publicar', self::pedido($e, $familia, max(1, (int) Cache::pull($contador, 1))));
        } catch (Throwable) {
            // O aviso nunca pode ser a razão de um pedido falhar.
        }
    }

    public static function pedido(Throwable $e, string $familia, int $incremento = 1): array
    {
        $mensagem = Limpeza::texto($e->getMessage());
        $ficheiro = Str::after($e->getFile(), base_path().'/');
        $telescope = class_exists(\Laravel\Telescope\Telescope::class) ? rtrim((string) config('app.url'), '/').'/'.trim((string) config('telescope.path', 'telescope'), '/').'/exceptions' : null;

        return [
            'canal' => config('honga-hub.discord.canal_forcado') ?: 'erros',
            'referencia' => "erro:{$familia}",
            'titulo' => Str::limit(class_basename($e).': '.($mensagem !== '' ? $mensagem : 'sem mensagem'), 250),
            'descricao' => Str::limit($mensagem, 1500)."\n```{$ficheiro}:{$e->getLine()}```",
            'nivel' => 'erro',
            'agrupar' => true,
            'incremento' => $incremento,
            'campos' => array_values(array_filter([
                ['nome' => 'Onde', 'valor' => Str::limit(self::onde(), 1000), 'linha' => true],
                ($u = self::utilizador()) ? ['nome' => 'Utilizador', 'valor' => $u, 'linha' => true] : null,
                ['nome' => 'Classe', 'valor' => Str::limit(get_class($e), 1000), 'linha' => false],
            ])),
            'ligacoes' => $telescope ? [['rotulo' => 'Abrir no Telescope', 'url' => $telescope]] : [],
            'botoes' => [
                ['accao' => 'hub.resolvido', 'rotulo' => 'Resolvido', 'estilo' => 'sucesso'],
                ['accao' => 'hub.silenciar', 'rotulo' => 'Silenciar 24 h', 'dados' => ['horas' => 24]],
                ['accao' => 'hub.silenciar', 'rotulo' => 'Silenciar 7 dias', 'dados' => ['horas' => 168]],
            ],
        ];
    }

    private static function onde(): string
    {
        if (self::$jobActual) {
            return 'Job '.class_basename(self::$jobActual);
        }

        if (app()->runningInConsole()) {
            return 'artisan '.implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 3));
        }

        $pedido = request();

        // Sem a query string: é lá que vêm tokens, pesquisas e telefones.
        return $pedido->method().' '.$pedido->getHost().'/'.ltrim($pedido->path(), '/');
    }

    private static function utilizador(): ?string
    {
        try {
            $u = Auth::user();
        } catch (Throwable) {
            return null;
        }

        return $u ? class_basename($u).' #'.$u->getAuthIdentifier() : null;
    }
}
