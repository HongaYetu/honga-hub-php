# hongayetu/honga-hub-php

SDK Laravel do **Honga Hub** (plataforma central de tempo-real e media): emissão de tokens de sessão, cliente s2s (chat/streaming), **barramento de eventos** e assinatura de conectores. Serve todos os serviços do ecossistema (Humbi, Escola Inteligente, Socia, Kanda...). Não depende do auth-sdk.

## Instalação

```bash
composer require hongayetu/honga-hub-php
php artisan vendor:publish --tag=honga-hub-config
```

`.env` do serviço (valores gerados no painel do Honga Hub):

```
HONGAHUB_CHAVE_SERVICO=humbi
HONGAHUB_JWT_SEGREDO=...
HONGAHUB_API_URL=https://hub.hongayetu.com
HONGAHUB_SOCKET_URL=https://hub.hongayetu.com
HONGAHUB_CONECTOR_SEGREDO=...   # se o serviço expõe context cards
HONGAHUB_API_KEY=...            # se o serviço usa s2s
```

## Endpoint de token (o único passo obrigatório)

```php
// app/MakaChat/ClienteResolver.php
class ClienteResolver implements \Hongayetu\HongaHub\IdentityResolver
{
    public function resolve(Request $request): ?array
    {
        $user = $request->user();

        return $user ? [
            'id' => (string) $user->id,
            'tipo' => 'cliente',
            'nome' => $user->name,
            'foto' => $user->foto_link(),
            'honga_user_id' => $user->honga_user_id,
            'metadados' => ['verificado' => $user->verificado],   // opcional: badge, username, ocultar_online...
        ] : null;
    }
}

// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::hubToken('/makachat/token', \App\MakaChat\ClienteResolver::class);
});
```

As apps passam `getToken: () => req('/api/makachat/token')` ao `<MakaChatProvider>` e está feito.

## Autorização de contacto (opcional)

Quando o serviço tem bloqueios/privacidade, o chat pergunta antes de deixar A contactar B (estratégia http; a alternativa `postgres` configura-se na central com uma consulta SQL direta):

```php
// app/MakaChat/PodeContactarResolver.php
class PodeContactarResolver implements \Hongayetu\HongaHub\AutorizacaoResolver
{
    public function resolve(array $de, array $para): bool|array
    {
        $bloqueado = Bloqueio::entre($de['id'], $para['id'])->exists();

        return $bloqueado ? ['permitido' => false, 'motivo' => 'Não podes contactar este utilizador'] : true;
    }
}

// routes/api.php (verificação HMAC incluída na macro)
Route::hubAutorizacao('/makachat/autorizacao', \App\MakaChat\PodeContactarResolver::class);
```

## Context cards (opcional)

```php
Route::get('/makachat/context/corrida/{id}', function (Request $request, $id) {
    abort_unless(\Hongayetu\HongaHub\ConnectorSignature::verify($request), 401);

    $corrida = Corrida::findOrFail($id);

    return response()->json([
        'titulo' => "Corrida #{$corrida->id}",
        'subtitulo' => $corrida->destino,
        'foto_url' => null,
        'linhas' => ["Estado: {$corrida->estado}"],
        'acoes' => [],
    ]);
});
```

## S2S (opcional)

Autenticado com `HONGAHUB_API_KEY` (headers `X-Maka-Service` + `X-Maka-Api-Key`). O servidor também aceita, em alternativa, um JWT HS256 assinado com o `jwt_segredo` do serviço e claims `iss` + `s2s: true`.

```php
$maka = app(\Hongayetu\HongaHub\HongaHubClient::class);

// criar/obter conversa (ex.: cliente ↔ negócio com contexto de encomenda)
$maka->criarConversa([
    'tipo' => 'privada',
    'participantes' => [
        ['id_externo' => (string) $cliente->id, 'tipo' => 'cliente', 'nome' => $cliente->name],
        ['id_externo' => (string) $negocio->id, 'tipo' => 'negocio', 'nome' => $negocio->nome],
    ],
    'contexto_tipo' => 'encomenda',
    'contexto_id' => (string) $encomenda->id,
]);

// mensagem de sistema na conversa
$maka->mensagemSistema($conversaId, "Encomenda #{$encomenda->codigo} — {$encomenda->estado}");
```

## Webhook de identidades (opcional)

Quando um perfil muda no serviço (nome, foto, dados extra), empurra a atualização em batch — o chat atualiza as identidades que já existem e **ignora as desconhecidas** (nascerão pelo login ou pela primeira conversa):

```php
// ex.: observer do Negocio
$maka->atualizarIdentidades([
    ['id_externo' => (string) $negocio->id, 'tipo' => 'negocio', 'nome' => $negocio->nome, 'foto' => $negocio->logo_url],
]);
// → ['estado' => 'ok', 'atualizadas' => 1, 'ignoradas' => 0]
```

Alternativa sem webhook: configurar no painel da central a **estratégia de resolução Postgres** do serviço (consulta SQL por tipo de identidade, TTL configurável) — o servidor refresca nome/foto diretamente da base do serviço nas leituras.

## Discord de administração (0.2)

O serviço publica no Discord da Honga Yetu **através do hub** — nunca fala com o
Discord. Usa o `HONGAHUB_CHAVE_SERVICO` e o `HONGAHUB_JWT_SEGREDO` que já tem.
Ligado por omissão só em produção (`HONGAHUB_DISCORD_ACTIVO`).

O que se ganha só por actualizar o pacote:

- **#erros** — cada excepção reportada, agrupada por ficheiro + linha, com as
  ocorrências contadas e os dados pessoais tapados;
- **#criticos** — tarefas agendadas que falham (e que fecham quando voltam a
  correr) e as chaves de log de `honga-hub.discord.logs_criticos`.

O que o serviço escreve:

```php
// Uma mensagem, ou um post num fórum *-pendentes com botões
use Hongayetu\HongaHub\Discord\Discord;

Discord::publicar([
    'canal' => 'salu-pendentes',
    'referencia' => "kyc:{$kyc->id}",
    'titulo' => 'Verificação de prestador',
    'botoes' => [
        ['accao' => 'kyc.aprovar', 'rotulo' => 'Aprovar', 'estilo' => 'sucesso', 'dados' => ['kyc_id' => $kyc->id]],
        ['accao' => 'kyc.recusar', 'rotulo' => 'Recusar', 'estilo' => 'perigo', 'pede_motivo' => true, 'dados' => ['kyc_id' => $kyc->id]],
    ],
]);

Discord::resolver("kyc:{$kyc->id}", 'Aprovado no painel');   // decidido fora do Discord
```

```php
// routes/api.php — o conector dos botões: <conector_base_url>/discord/accao
Route::hubDiscordAccoes('/discord/accao');

// config/honga-hub.php
'discord' => ['accoes' => ['kyc.aprovar' => AprovarKyc::class, ...]],
```

Cada acção implementa `Discord\Accoes\AccaoDiscord` e decide quem pode:
`$pedido->hongaUserId` é a conta Honga Yetu (`public.users.id`) ligada ao
Discord de quem clicou. Resumo diário: secções `Discord\Resumo\Seccao` em
`discord.resumo`, publicadas às 23:00 (`hub:discord-resumo --simular` para ver).
