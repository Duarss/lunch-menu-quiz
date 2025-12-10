<?php

namespace App\Actions\User;

class NormalizeUserPayload extends UserAction
{
    public function __invoke(array $request, bool $forUpdate = false): object
    {
        $payload = $this->normalizePayload($request, $forUpdate);

        return (object) $payload;
    }
}
