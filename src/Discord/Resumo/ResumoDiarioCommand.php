<?php

namespace Hongayetu\HongaHub\Discord\Resumo;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ResumoDiarioCommand extends Command
{
    protected $signature = 'hub:discord-resumo {--data= : O dia (AAAA-MM-DD)} {--simular : Mostra no terminal, não publica}';

    protected $description = 'Publica no #resumo-diario do Discord o resumo do dia deste serviço';

    public function handle(ResumoDiario $resumo): int
    {
        $dia = $this->option('data') ? Carbon::parse($this->option('data')) : Carbon::now();

        if ($this->option('simular')) {
            foreach ($resumo->montar($dia) as $m) {
                $this->info('■ '.$m['seccao']->titulo());
                foreach ($m['pedido']['campos'] ?? [] as $c) {
                    $this->line("  <comment>{$c['nome']}</comment>: ".str_replace("\n", "\n      ", $c['valor']));
                }
                if ($m['pedido'] === null) {
                    $this->line('  (sem actividade)');
                }
            }

            return self::SUCCESS;
        }

        $this->info('Secções com actividade publicadas: '.$resumo->publicar($dia));

        return self::SUCCESS;
    }
}
