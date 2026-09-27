<?php

namespace Hongayetu\HongaHub\Discord\Resumo;

final class Numeros
{
    public static function kz(float|int|string|null $valor): string
    {
        return number_format((float) $valor, 2, ',', '.').' Kz';
    }

    public static function n(int|float|string|null $valor): string
    {
        return number_format((float) $valor, 0, ',', '.');
    }

    public static function comOntem(int|float $hoje, int|float $ontem, bool $dinheiro = false): string
    {
        $texto = $dinheiro ? self::kz($hoje) : self::n($hoje);
        $d = $hoje - $ontem;

        if (abs($d) < 0.005) {
            return $texto;
        }

        return $texto.' ('.($d > 0 ? '▲' : '▼').($dinheiro ? self::kz(abs($d)) : self::n(abs($d))).' vs ontem)';
    }

    public static function linhas(array $linhas, string $vazio = '—'): string
    {
        $texto = '';

        foreach ($linhas as $linha) {
            if (mb_strlen($texto) + mb_strlen($linha) + 1 > 1000) {
                return $texto.'…';
            }

            $texto .= ($texto === '' ? '' : "\n").$linha;
        }

        return $texto === '' ? $vazio : $texto;
    }
}
