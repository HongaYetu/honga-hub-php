<?php

namespace Hongayetu\HongaHub\Discord\Accoes;

/**
 * Uma acção de um botão do Discord, registada em `honga-hub.discord.accoes`.
 * Quem a implementa decide tudo: se aquela pessoa pode (`$pedido->hongaUserId`
 * é a conta Honga Yetu de quem clicou), se o caso ainda está pendente, e o que
 * responder. O texto da resposta é o que aparece a quem clicou.
 */
interface AccaoDiscord
{
    public function executar(PedidoDeAccao $pedido): RespostaDeAccao;
}
