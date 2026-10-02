<?php

namespace ChrisHenrique\RequestsMonitor\Contracts;

use Illuminate\Http\Request;

interface SlowRequestCollector
{
    /**
     * Coleta informações de uma requisição considerada lenta.
     *
     * @param  array{execution_ms: float, threshold_ms: float}  $context
     * @return array  Dados que serão armazenados em content['slow'][<chave>]
     */
    public function handle(Request $request, array $context): array;
}
