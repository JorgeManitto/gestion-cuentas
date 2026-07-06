<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Presencia estilo "heartbeat" de WordPress: qué usuarios están mirando una
 * orden ahora mismo. Se apoya en el Cache (driver database) — sin migraciones.
 *
 * Cada cliente late cada INTERVAL segundos; si deja de latir por más de
 * ACTIVE_WINDOW segundos (pestaña cerrada, sin red, dormido) se lo poda solo.
 */
class OrderPresence
{
    /** Cada cuánto late el cliente (segundos). El front lo lee de acá. */
    public const INTERVAL = 15;

    /** Ventana en la que un usuario se considera "presente" (segundos). */
    private const ACTIVE_WINDOW = 45;

    /** Cuánto vive la entrada de caché si nadie más late (segundos). */
    private const TTL = 300;

    /**
     * Registra (o refresca) la presencia del usuario en la orden y devuelve
     * la lista de OTROS usuarios presentes.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function beat(int $orderId, int $userId, string $userName): array
    {
        return $this->mutate($orderId, $userId, function (array $viewers) use ($userId, $userName) {
            $viewers[$userId] = ['name' => $userName, 'seen' => time()];
            return $viewers;
        });
    }

    /**
     * Quita al usuario de la orden (al cerrar/abandonar la pestaña).
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function leave(int $orderId, int $userId): array
    {
        return $this->mutate($orderId, $userId, function (array $viewers) use ($userId) {
            unset($viewers[$userId]);
            return $viewers;
        });
    }

    /**
     * Lee/modifica el mapa de presencia bajo lock (read-modify-write atómico),
     * poda a los inactivos y devuelve los otros presentes.
     *
     * @return array<int, array{id:int, name:string}>
     */
    private function mutate(int $orderId, int $selfId, Closure $apply): array
    {
        $key = $this->key($orderId);
        $viewers = [];

        // El lock evita que dos latidos simultáneos se pisen el array.
        Cache::lock($key . ':lock', 5)->block(3, function () use ($key, $apply, &$viewers) {
            $viewers = $this->prune(Cache::get($key, []));
            $viewers = $apply($viewers);

            if ($viewers) {
                Cache::put($key, $viewers, self::TTL);
            } else {
                Cache::forget($key);
            }
        });

        return $this->others($viewers, $selfId);
    }

    /** Descarta a los que no laten hace más de ACTIVE_WINDOW segundos. */
    private function prune(array $viewers): array
    {
        $cutoff = time() - self::ACTIVE_WINDOW;

        return array_filter($viewers, fn ($v) => ($v['seen'] ?? 0) >= $cutoff);
    }

    /**
     * @return array<int, array{id:int, name:string}>
     */
    private function others(array $viewers, int $selfId): array
    {
        return collect($viewers)
            ->except($selfId)
            ->map(fn ($v, $id) => ['id' => (int) $id, 'name' => $v['name']])
            ->values()
            ->all();
    }

    private function key(int $orderId): string
    {
        return "order:presence:{$orderId}";
    }
}
