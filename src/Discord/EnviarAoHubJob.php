<?php

namespace Hongayetu\HongaHub\Discord;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Leva um pedido ao hub. **Nunca lança**: uma falha que chegasse ao handler de
 * excepções voltaria ao Discord por este mesmo job — um ciclo, justamente com o
 * hub em baixo. Repete com `release` e no fim deixa uma linha no log, sem
 * excepção anexada.
 */
class EnviarAoHubJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    private const ESPERAS = [10, 30, 60, 180];

    public function __construct(public string $caminho, public array $corpo) {}

    public function handle(): void
    {
        try {
            Discord::enviarAgora($this->caminho, $this->corpo);
        } catch (Throwable $e) {
            if ($this->attempts() < $this->tries) {
                $this->release(self::ESPERAS[$this->attempts() - 1] ?? 180);

                return;
            }

            Log::warning('Discord: pedido ao hub desistido.', [
                'caminho' => $this->caminho,
                'referencia' => $this->corpo['referencia'] ?? null,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}
