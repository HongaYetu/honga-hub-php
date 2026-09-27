<?php

namespace Hongayetu\HongaHub\Discord\Accoes;

/** O clique, tal como o hub o entrega. `hongaUserId` é o `public.users.id` da conta Honga Yetu ligada ao Discord. */
final class PedidoDeAccao
{
    public function __construct(
        public readonly string $accao,
        public readonly string $referencia,
        public readonly array $dados,
        public readonly ?string $motivo,
        public readonly ?string $valor,
        public readonly int $hongaUserId,
        public readonly string $discordId,
        public readonly string $nome,
    ) {}

    public static function doCorpo(array $corpo): self
    {
        return new self(
            accao: (string) $corpo['accao'],
            referencia: (string) $corpo['referencia'],
            dados: (array) ($corpo['dados'] ?? []),
            motivo: isset($corpo['motivo']) ? trim((string) $corpo['motivo']) : null,
            valor: isset($corpo['valor']) ? (string) $corpo['valor'] : null,
            hongaUserId: (int) $corpo['utilizador']['user_id'],
            discordId: (string) $corpo['utilizador']['discord_id'],
            nome: (string) $corpo['utilizador']['nome'],
        );
    }

    public function dado(string $chave): mixed
    {
        return $this->dados[$chave] ?? null;
    }

    /** O motivo escrito no formulário, já limpo e cortado. */
    public function motivo(): string
    {
        return mb_substr(trim((string) $this->motivo), 0, 500);
    }
}
