<?php

namespace Hongayetu\HongaHub\Discord;

/**
 * O Discord é um serviço de fora e guarda tudo o que lá passa: tapam-se emails,
 * números longos (telefones, BI, contas) e os valores entre plicas do SQL.
 */
final class Limpeza
{
    public static function texto(string $texto): string
    {
        $texto = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $texto) ?? $texto;
        $texto = preg_replace('/\b[A-Z0-9]{0,4}\d{7,}[A-Z0-9]{0,4}\b/i', '[número]', $texto) ?? $texto;
        $texto = preg_replace("/'[^']{1,200}'/", "'…'", $texto) ?? $texto;

        return trim($texto);
    }

    /** O contexto de uma linha de log, sem dados pessoais e a caber num campo. */
    public static function contexto(array $contexto): ?string
    {
        $linhas = [];

        foreach ($contexto as $k => $v) {
            if ($k === 'exception') {
                continue;
            }

            $linhas[] = "{$k}: ".mb_strimwidth(self::texto(trim((string) json_encode($v, JSON_UNESCAPED_UNICODE), '"')), 0, 150, '…');
        }

        return $linhas === [] ? null : mb_strimwidth(implode("\n", $linhas), 0, 1000, '…');
    }
}
