<?php

namespace Hongayetu\HongaHub\Discord\Resumo;

use Hongayetu\HongaHub\Discord\Discord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * O resumo do dia de um serviço no #resumo-diario: uma mensagem por secção, por
 * ordem, com o nome do serviço no título. Uma secção que rebenta aparece com o
 * erro sem levar as outras; correr o mesmo dia outra vez edita.
 */
class ResumoDiario
{
    /** @return list<array{seccao: Seccao, pedido: array|null}> */
    public function montar(Carbon $dia): array
    {
        $inicio = $dia->copy()->startOfDay();
        $fim = $inicio->copy()->endOfDay();
        $servico = (string) config('honga-hub.discord.nome_servico', config('app.name'));
        $mensagens = [];

        foreach ((array) config('honga-hub.discord.resumo', []) as $classe) {
            /** @var Seccao $seccao */
            $seccao = app($classe);

            try {
                $dados = $seccao->calcular($inicio, $fim);
            } catch (Throwable $e) {
                report($e);
                $dados = ['descricao' => '⚠️ Não foi possível calcular esta secção: '.Str::limit($e->getMessage(), 300), 'campos' => []];
            }

            $mensagens[] = ['seccao' => $seccao, 'pedido' => $dados === null ? null : array_filter([
                'canal' => 'resumo-diario',
                'referencia' => 'resumo:'.$inicio->toDateString().':'.config('honga-hub.chave_servico').':'.$seccao->chave(),
                'titulo' => Str::limit($seccao->titulo().' · '.$servico, 250),
                'descricao' => $dados['descricao'] ?? null,
                'nivel' => 'info',
                'campos' => array_slice($dados['campos'] ?? [], 0, 20),
            ], fn ($v) => $v !== null)];
        }

        return $mensagens;
    }

    public function publicar(Carbon $dia): int
    {
        $pedidos = array_values(array_filter(array_map(fn ($m) => $m['pedido'], $this->montar($dia))));
        Discord::publicarEmSequencia($pedidos);

        return count($pedidos);
    }
}
