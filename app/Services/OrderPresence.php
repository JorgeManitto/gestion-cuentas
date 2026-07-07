<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Presencia + control exclusivo estilo "heartbeat" de WordPress.
 *
 * Además de saber qué usuarios están mirando una orden, un solo usuario tiene
 * el CONTROL de la orden a la vez (el que puede operar). El primero que entra
 * lo toma; otro puede arrebatárselo explícitamente (botón "Tomar el control").
 *
 * Se apoya en el Cache (driver database) — sin migraciones. Cada cliente late
 * cada INTERVAL segundos; si deja de latir por más de ACTIVE_WINDOW segundos
 * (pestaña cerrada, sin red, dormido) se lo poda solo y libera el control.
 *
 * Estructura en caché por orden:
 *   ['controller' => ?int userId, 'viewers' => [userId => ['name','seen']]]
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
     * Registra/refresca la presencia del usuario y resuelve el control.
     *
     * @param bool $take Si true, el usuario ARREBATA el control (lo pidió explícitamente).
     *                   Si false, solo lo toma cuando no hay nadie con control activo.
     * @return array{viewers:array<int,array{id:int,name:string}>, controller:?array{id:int,name:string}, has_control:bool}
     */
    public function beat(int $orderId, int $userId, string $userName, bool $take = false): array
    {
        return $this->mutate($orderId, $userId, function (array $state) use ($userId, $userName, $take) {
            $state['viewers'][$userId] = ['name' => $userName, 'seen' => time()];

            // Toma el control si lo pidió, o si nadie lo tiene ahora mismo.
            if ($take || $state['controller'] === null) {
                $state['controller'] = $userId;
            }

            return $state;
        });
    }

    /**
     * Quita al usuario de la orden (al cerrar/abandonar la pestaña) y, si tenía
     * el control, lo libera para que otro presente lo tome en su próximo latido.
     *
     * @return array{viewers:array<int,array{id:int,name:string}>, controller:?array{id:int,name:string}, has_control:bool}
     */
    public function leave(int $orderId, int $userId): array
    {
        return $this->mutate($orderId, $userId, function (array $state) use ($userId) {
            unset($state['viewers'][$userId]);

            if ($state['controller'] === $userId) {
                $state['controller'] = null;
            }

            return $state;
        });
    }

    /**
     * Quién tiene el control de cada orden (solo lectura, sin lock ni escritura).
     * Pensado para pintar el candado en el listado.
     *
     * @param  array<int,int> $orderIds
     * @return array<int, array{id:int, name:string}>  orderId => holder
     */
    public function holdersFor(array $orderIds): array
    {
        $out = [];

        foreach (array_unique($orderIds) as $orderId) {
            $state = $this->prune($this->normalize(Cache::get($this->key($orderId), [])));
            $cid   = $state['controller'];

            if ($cid !== null && isset($state['viewers'][$cid])) {
                $out[$orderId] = ['id' => (int) $cid, 'name' => $state['viewers'][$cid]['name']];
            }
        }

        return $out;
    }

    /**
     * Lee/modifica el estado bajo lock (read-modify-write atómico), poda a los
     * inactivos y devuelve el payload de presencia + control para el usuario.
     */
    private function mutate(int $orderId, int $selfId, Closure $apply): array
    {
        $key   = $this->key($orderId);
        $state = ['controller' => null, 'viewers' => []];

        // El lock evita que dos latidos simultáneos se pisen el estado.
        Cache::lock($key . ':lock', 5)->block(3, function () use ($key, $apply, &$state) {
            $state = $this->prune($this->normalize(Cache::get($key, [])));
            $state = $apply($state);

            if ($state['viewers']) {
                Cache::put($key, $state, self::TTL);
            } else {
                Cache::forget($key);
            }
        });

        return $this->payload($state, $selfId);
    }

    /**
     * Descarta a los que no laten hace más de ACTIVE_WINDOW segundos.
     * Si el que tenía el control cayó, el control queda libre.
     */
    private function prune(array $state): array
    {
        $cutoff = time() - self::ACTIVE_WINDOW;

        $state['viewers'] = array_filter(
            $state['viewers'],
            fn ($v) => ($v['seen'] ?? 0) >= $cutoff
        );

        if ($state['controller'] !== null && ! isset($state['viewers'][$state['controller']])) {
            $state['controller'] = null;
        }

        return $state;
    }

    /**
     * Arma la respuesta para el usuario que late: otros presentes, quién tiene
     * el control y si soy yo.
     *
     * @return array{viewers:array<int,array{id:int,name:string}>, controller:?array{id:int,name:string}, has_control:bool}
     */
    private function payload(array $state, int $selfId): array
    {
        $viewers = $state['viewers'];
        $cid     = $state['controller'];

        $controller = ($cid !== null && isset($viewers[$cid]))
            ? ['id' => (int) $cid, 'name' => $viewers[$cid]['name']]
            : null;

        return [
            'viewers'     => $this->others($viewers, $selfId),
            'controller'  => $controller,
            'has_control' => $cid === $selfId,
        ];
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

    /**
     * Tolera el formato viejo (solo mapa de viewers) y normaliza al nuevo shape.
     */
    private function normalize($raw): array
    {
        if (is_array($raw) && array_key_exists('viewers', $raw)) {
            return [
                'controller' => $raw['controller'] ?? null,
                'viewers'    => is_array($raw['viewers']) ? $raw['viewers'] : [],
            ];
        }

        return ['controller' => null, 'viewers' => is_array($raw) ? $raw : []];
    }

    private function key(int $orderId): string
    {
        return "order:presence:{$orderId}";
    }
}
