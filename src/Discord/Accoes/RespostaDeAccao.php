<?php

namespace Hongayetu\HongaHub\Discord\Accoes;

final class RespostaDeAccao
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $texto,
        public readonly ?bool $resolver = null,
        public readonly ?string $etiqueta = null,
    ) {}

    /** Feito. Por omissão fecha o caso no Discord; `resolver: false` deixa-o aberto. */
    public static function ok(string $texto, ?string $etiqueta = null, bool $resolver = true): self
    {
        return new self(true, $texto, $resolver, $etiqueta);
    }

    public static function erro(string $texto): self
    {
        return new self(false, $texto);
    }

    public function paraJson(): array
    {
        return array_filter([
            'estado' => $this->ok ? 'ok' : 'erro',
            'texto' => $this->texto,
            'resolver' => $this->ok ? $this->resolver : null,
            'etiqueta' => $this->etiqueta,
        ], fn ($v) => $v !== null);
    }
}
