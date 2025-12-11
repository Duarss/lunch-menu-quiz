<?php

namespace App\Actions\User;

class NormalizeUserPayload extends UserAction
{
    // Note: __invoke normalizes the user payload for creation or update
    public function __invoke(array $request, bool $forUpdate = false): object
    {
        $payload = $this->normalizePayload($request, $forUpdate);

        return (object) $payload;
    }
}
