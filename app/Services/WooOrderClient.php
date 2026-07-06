<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooOrderClient
{
    /**
     * Cambia el estado de una orden vía el endpoint del plugin:
     *   POST {base}/wp-json/miapi/v1/cambiar-estado/{id}
     *   Authorization: Bearer {secret}
     *   body: { "estado": "completed" }
     */
    public function setStatus(string|int $wcOrderId, string|null $estado): bool
    {
        $base = rtrim((string) config('services.woo.base_url'), '/');
        $url  = "{$base}/wp-json/miapi/v1/cambiar-estado/{$wcOrderId}";

        try {
            $response = Http::withToken(config('services.woo.secret'))
                ->timeout(15)
                ->retry(2, 300)              // 2 reintentos, 300ms entre cada uno
                ->acceptJson()
                ->post($url, ['estado' => $estado]);

            if ($response->successful() && data_get($response->json(), 'success')) {
                return true;
            }

            Log::error('WooOrderClient: respuesta no exitosa', [
                'wc_order_id' => $wcOrderId,
                'estado'      => $estado,
                'status_code' => $response->status(),
                'body'        => $response->body(),
            ]);
            return false;

        } catch (\Throwable $e) {
            Log::error('WooOrderClient: excepción al llamar a Woo', [
                'wc_order_id' => $wcOrderId,
                'estado'      => $estado,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }
}