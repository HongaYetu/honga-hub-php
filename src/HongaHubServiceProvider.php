<?php

namespace Hongayetu\HongaHub;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class HongaHubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/honga-hub.php', 'honga-hub');
        $this->app->singleton(TokenIssuer::class);
        $this->app->singleton(HongaHubClient::class);
    }

    public function boot(): void
    {
        $this->arrancarDiscord();

        $this->publishes([__DIR__.'/../config/honga-hub.php' => config_path('honga-hub.php')], 'honga-hub-config');

        /**
         * Route::hubToken('/hub/token', MeuIdentityResolver::class)
         * — regista o endpoint de troca de sessão por token de chat.
         */
        Route::macro('hubToken', function (string $uri, string $resolverClass) {
            return Route::post($uri, function (Request $request) use ($resolverClass) {
                /** @var IdentityResolver $resolver */
                $resolver = app($resolverClass);
                $identidade = $resolver->resolve($request);

                if ($identidade === null) {
                    return response()->json(['estado' => 'erro', 'texto' => 'Não autenticado'], 401);
                }

                return response()->json(app(TokenIssuer::class)->resposta(
                    $identidade['id'],
                    $identidade['tipo'],
                    $identidade['nome'],
                    $identidade['foto'] ?? null,
                    $identidade['honga_user_id'] ?? null,
                    $identidade['metadados'] ?? null,
                ));
            });
        });

        /**
         * Route::hubAutorizacao('/hub/autorizacao', MeuAutorizacaoResolver::class)
         * — endpoint do conector `autorizacao` (estratégia http): o Honga Hub
         * pergunta, com assinatura HMAC, se A pode contactar B.
         */
        Route::macro('hubAutorizacao', function (string $uri, string $resolverClass) {
            return Route::get($uri, function (Request $request) use ($resolverClass) {
                if (! ConnectorSignature::verify($request)) {
                    return response()->json(['permitido' => false, 'motivo' => 'Assinatura inválida'], 401);
                }

                /** @var AutorizacaoResolver $resolver */
                $resolver = app($resolverClass);
                $decisao = $resolver->resolve(
                    ['tipo' => (string) $request->query('de_tipo'), 'id' => (string) $request->query('de_id')],
                    ['tipo' => (string) $request->query('para_tipo'), 'id' => (string) $request->query('para_id')],
                );

                if (is_bool($decisao)) {
                    return response()->json(['permitido' => $decisao]);
                }

                return response()->json([
                    'permitido' => (bool) ($decisao['permitido'] ?? false),
                    'motivo' => $decisao['motivo'] ?? null,
                ]);
            });
        });
    }

    /**
     * O Discord de administração: o #erros pelo handler de excepções, os vigias
     * das tarefas agendadas e dos logs de dinheiro, a rota dos botões e o resumo
     * diário. Tudo inerte com `discord.activo` a falso.
     */
    private function arrancarDiscord(): void
    {
        // `callAfterResolving` corre já se o handler (ou o Schedule) tiver sido
        // resolvido antes deste provider arrancar, e depois se não.
        $this->callAfterResolving(\Illuminate\Contracts\Debug\ExceptionHandler::class, function ($handler) {
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(fn (\Throwable $e) => \Hongayetu\HongaHub\Discord\ErrosNoDiscord::reportar($e));
            }
        });

        $eventos = $this->app['events'];
        $vigias = \Hongayetu\HongaHub\Discord\VigiasDoServico::class;
        $eventos->listen(\Illuminate\Console\Events\ScheduledTaskFinished::class, [$vigias, 'tarefaFinalizada']);
        $eventos->listen(\Illuminate\Console\Events\ScheduledBackgroundTaskFinished::class, [$vigias, 'tarefaFinalizada']);
        $eventos->listen(\Illuminate\Console\Events\ScheduledTaskFailed::class, [$vigias, 'tarefaFalhou']);
        $eventos->listen(\Illuminate\Log\Events\MessageLogged::class, [$vigias, 'log']);
        $eventos->listen(\Illuminate\Queue\Events\JobProcessing::class, fn ($e) => \Hongayetu\HongaHub\Discord\ErrosNoDiscord::$jobActual = $e->job->resolveName());
        $eventos->listen(\Illuminate\Queue\Events\JobProcessed::class, fn () => \Hongayetu\HongaHub\Discord\ErrosNoDiscord::$jobActual = null);

        /**
         * Route::hubDiscordAccoes('/discord/accao') — o conector dos botões. O
         * caminho tem de ser `<conector_base_url do serviço no hub>/discord/accao`,
         * num grupo sem CSRF nem sessão (o `api`).
         */
        Route::macro('hubDiscordAccoes', fn (string $uri = '/discord/accao') => Route::post($uri, \Hongayetu\HongaHub\Discord\Accoes\ConectorDeAccoes::class)->name('honga-hub.discord.accao'));

        if ($this->app->runningInConsole()) {
            $this->commands([\Hongayetu\HongaHub\Discord\Resumo\ResumoDiarioCommand::class]);

            $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
                if (config('honga-hub.discord.resumo')) {
                    $schedule->command('hub:discord-resumo')
                        ->dailyAt((string) config('honga-hub.discord.resumo_hora', '23:00'))
                        ->name('hub-discord-resumo')
                        ->withoutOverlapping()
                        ->onOneServer();
                }
            });
        }
    }
}
