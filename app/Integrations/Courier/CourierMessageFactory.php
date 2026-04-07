<?php
namespace App\Integrations\Courier;

use App\DTO\RenderedEmailDto;

class CourierMessageFactory
{ // create payload
    public function make(
        string $tenantId,
        string $idempotencyKey,
        array $receivers,
        RenderedEmailDto $email
    ): array {
        return [
            'tenant_id' => $tenantId,
            'idempotency_key' => $idempotencyKey,
            'to' => $receivers[0] ?? null,
            'from' => 'noreply@proxicore.com',
            'subject' => $email->subject,
            'body'=>$email->content_html
        ];
    }
}
