<?php

namespace Hongayetu\HongaHub\Discord\Accoes;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * `POST <conector_base_url>/discord/accao`: os cliques que o hub encaminha.
 *
 * Só o hub o chama, com um JWT de 60 s assinado com o `jwt_segredo` do serviço
 * (`iss = honga-hub`, `aud = <chave_servico>`, `discord = true`). A resposta é
 * sempre `{estado, texto}` e sempre 200 quando a assinatura passa: o texto de
 * um erro é o que aparece a quem clicou.
 */
class ConectorDeAccoes
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->assinadoPeloHub((string) $request->bearerToken())) {
            return response()->json(['estado' => 'erro', 'texto' => 'Pedido não assinado pelo hub.'], 401);
        }

        $validacao = Validator::make($request->all(), [
            'accao' => ['required', 'string', 'max:60'],
            'referencia' => ['required', 'string', 'max:200'],
            'dados' => ['nullable', 'array'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'valor' => ['nullable', 'string', 'max:100'],
            'utilizador.user_id' => ['required', 'integer'],
            'utilizador.discord_id' => ['required', 'string'],
            'utilizador.nome' => ['required', 'string'],
        ]);

        if ($validacao->fails()) {
            return response()->json(RespostaDeAccao::erro('Pedido inválido: '.$validacao->errors()->first())->paraJson());
        }

        $classe = config('honga-hub.discord.accoes')[$request->input('accao')] ?? null;

        if (! $classe) {
            return response()->json(RespostaDeAccao::erro('Acção desconhecida neste serviço: '.$request->input('accao'))->paraJson());
        }

        $pedido = PedidoDeAccao::doCorpo($request->all());

        try {
            /** @var AccaoDiscord $accao */
            $accao = app($classe);
            $resposta = $accao->executar($pedido);
        } catch (Throwable $e) {
            report($e);
            $resposta = RespostaDeAccao::erro('Erro ao executar: '.mb_substr($e->getMessage(), 0, 300));
        }

        Log::info('Discord: acção executada.', [
            'accao' => $pedido->accao,
            'referencia' => $pedido->referencia,
            'honga_user_id' => $pedido->hongaUserId,
            'discord_id' => $pedido->discordId,
            'ok' => $resposta->ok,
        ]);

        return response()->json($resposta->paraJson());
    }

    private function assinadoPeloHub(string $token): bool
    {
        $segredo = (string) config('honga-hub.jwt_segredo');

        if ($token === '' || $segredo === '') {
            return false;
        }

        try {
            $claims = JWT::decode($token, new Key($segredo, 'HS256'));
        } catch (Throwable) {
            return false;
        }

        return ($claims->iss ?? null) === 'honga-hub'
            && ($claims->aud ?? null) === config('honga-hub.chave_servico')
            && ($claims->discord ?? false) === true;
    }
}
