<?php

namespace Hongayetu\HongaHub\Discord;

use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Str;

/**
 * Os dois vigias que qualquer serviço ganha só por instalar o SDK:
 *
 * - **Tarefas agendadas:** a que falha abre um alerta no #criticos (com menção
 *   se o comando for de dinheiro — `discord.tarefas_de_dinheiro`); a execução
 *   seguinte bem sucedida fecha-o. Um comando que falha no cron não dá erro a
 *   ninguém: dá uma linha no log.
 * - **Logs de dinheiro preso:** as chaves de log listadas em
 *   `discord.logs_criticos` (chave => o que quer dizer) chegam ao canal.
 */
class VigiasDoServico
{
    public function tarefaFinalizada(ScheduledTaskFinished|ScheduledBackgroundTaskFinished $evento): void
    {
        // Em segundo plano, o ScheduledTaskFinished sai no lançamento, sem código
        // de saída: contado como sucesso, fechava e reabria o alerta a cada volta.
        if ($evento instanceof ScheduledTaskFinished && $evento->task->runInBackground) {
            return;
        }

        $this->tarefa($evento->task, (int) ($evento->task->exitCode ?? 0), null);
    }

    public function tarefaFalhou(ScheduledTaskFailed $evento): void
    {
        if (str_starts_with($evento->exception->getMessage(), 'Scheduled command [')) {
            return;
        }

        $this->tarefa($evento->task, 1, $evento->exception->getMessage());
    }

    public function log(MessageLogged $evento): void
    {
        $mapa = (array) config('honga-hub.discord.logs_criticos', []);

        if (! isset($mapa[$evento->message]) || ! Discord::activo()) {
            return;
        }

        try {
            AlertasCriticos::abrir('log:'.$evento->message, (string) $mapa[$evento->message], null, array_values(array_filter([
                ['nome' => 'Registo', 'valor' => '`'.$evento->message.'` ('.$evento->level.')'],
                ($c = Limpeza::contexto($evento->context)) ? ['nome' => 'Contexto (a última ocorrência)', 'valor' => $c] : null,
            ])));
        } catch (\Throwable) {
            // Um aviso que falha não pode partir o código que escreveu o log.
        }
    }

    private function tarefa(Event $tarefa, int $codigo, ?string $erro): void
    {
        if (! Discord::activo()) {
            return;
        }

        $nome = self::nome($tarefa);
        $chave = 'agendado:'.Str::slug($nome);

        if ($codigo === 0 && $erro === null) {
            AlertasCriticos::fechar($chave, "«{$nome}» voltou a correr sem erro.");

            return;
        }

        $dinheiro = Str::contains(strtolower($nome), (array) config('honga-hub.discord.tarefas_de_dinheiro', []));

        AlertasCriticos::abrir($chave, "Tarefa agendada falhou: {$nome}", $dinheiro ? 'É uma tarefa de dinheiro: o que ela devia fazer pode não ter acontecido.' : null, array_values(array_filter([
            ['nome' => 'Código de saída', 'valor' => (string) $codigo, 'linha' => true],
            ['nome' => 'Agendada', 'valor' => $tarefa->expression.($tarefa->timezone ? " ({$tarefa->timezone})" : ''), 'linha' => true],
            $erro ? ['nome' => 'Erro', 'valor' => Str::limit(Limpeza::texto($erro), 1000)] : null,
        ])), mencionar: $dinheiro);
    }

    public static function nome(Event $tarefa): string
    {
        if ($tarefa->description) {
            return $tarefa->description;
        }

        $comando = (string) $tarefa->command;

        return trim(preg_replace("/^.*?'artisan'\\s*/", '', $comando) ?: $comando) ?: 'tarefa sem nome';
    }
}
