<?php

namespace Hongayetu\HongaHub\Discord;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * O #criticos: o que parte sem ninguém dar por isso. Cada alerta tem uma chave;
 * `abrir` com a mesma chave conta mais uma ocorrência na mesma mensagem, `fechar`
 * dá-a por resolvida quando a condição passa. O estado vive na cache.
 */
class AlertasCriticos
{
    public const CANAL = 'criticos';

    public static function aberto(string $chave): bool
    {
        return (bool) Cache::get("hub-discord:critico:{$chave}");
    }

    /** @param  list<array{nome: string, valor: string, linha?: bool}>  $campos */
    public static function abrir(string $chave, string $titulo, ?string $descricao = null, array $campos = [], bool $mencionar = true): void
    {
        $primeira = ! self::aberto($chave);
        Cache::put("hub-discord:critico:{$chave}", true, 7 * 86400);

        Discord::publicar(array_filter([
            'canal' => self::CANAL,
            'referencia' => 'critico:'.$chave,
            'titulo' => Str::limit('🚨 '.$titulo, 250),
            'descricao' => $descricao ? Str::limit($descricao, 3500) : null,
            'nivel' => 'critico',
            'agrupar' => true,
            'campos' => array_slice($campos, 0, 20),
            'mencionar' => $mencionar && $primeira ? 'plantao' : null,
            'botoes' => [
                ['accao' => 'hub.resolvido', 'rotulo' => 'Resolvido', 'estilo' => 'sucesso'],
                ['accao' => 'hub.silenciar', 'rotulo' => 'Silenciar 1 h', 'dados' => ['horas' => 1]],
                ['accao' => 'hub.silenciar', 'rotulo' => 'Silenciar 24 h', 'dados' => ['horas' => 24]],
            ],
        ], fn ($v) => $v !== null && $v !== []));
    }

    public static function fechar(string $chave, string $texto): void
    {
        if (! self::aberto($chave)) {
            return;
        }

        Cache::forget("hub-discord:critico:{$chave}");
        Discord::resolver('critico:'.$chave, $texto, true, 'Resolvido');
    }
}
