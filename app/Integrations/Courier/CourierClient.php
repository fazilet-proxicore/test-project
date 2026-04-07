<?php
namespace App\Integrations\Courier;

use Exception;
use Illuminate\Support\Facades\Http;
use Mail;

class CourierClient
{
    public function send(array $payload): void
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://courier.test/api/v1/emails/send', $payload);

    if($response->failed()){
        throw new Exception("Courier API error :" . $response->body());
    }

}
}
