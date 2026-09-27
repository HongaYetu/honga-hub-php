<?php

namespace Hongayetu\HongaHub\Discord\Resumo;

use Illuminate\Support\Carbon;

/**
 * Uma secção do resumo diário de um serviço. `calcular` recebe o dia (00:00 →
 * 23:59:59, na timezone da app) e devolve os campos, ou `null` quando não houve
 * nada — um serviço parado não precisa de uma mensagem a dizer zero.
 */
abstract class Seccao
{
    abstract public function chave(): string;

    abstract public function titulo(): string;

    /** @return array{descricao?: string, campos: list<array{nome: string, valor: string, linha?: bool}>}|null */
    abstract public function calcular(Carbon $inicio, Carbon $fim): ?array;

    protected function ontem(Carbon $inicio, Carbon $fim): array
    {
        return [$inicio->copy()->subDay(), $fim->copy()->subDay()];
    }

    /** Uma métrica isolada: uma tabela que falte dá `null` em vez de levar a secção. */
    protected function valor(callable $calculo, mixed $omissao = null): mixed
    {
        try {
            return $calculo();
        } catch (\Throwable $e) {
            report($e);

            return $omissao;
        }
    }
}
