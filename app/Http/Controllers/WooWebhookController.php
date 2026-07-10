<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\WooProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WooWebhookController extends Controller
{
    public function store(Request $request)
    {
        // 1) Verificar la firma sobre el cuerpo CRUDO (no re-serializado).
        //    Tiene que calcularse sobre exactamente los mismos bytes que firmó WP.
        $raw       = $request->getContent();
        $signature = (string) $request->header('X-Woo-Signature', '');
        $expected  = hash_hmac('sha256', $raw, (string) config('services.woo.secret'));

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Firma inválida');
        }

        // 2) Validar el payload.
        $data = $request->validate([
            'id'           => ['required', 'integer'],
            'name'         => ['required', 'string'],
            'sku'          => ['nullable', 'string'],
            'status'       => ['nullable', 'string'],
            'categories'   => ['array'],
            'categories.*' => ['string'],
            'image_url'    => ['nullable', 'url'],
        ]);

        // 3) Resolver la plataforma: primero por categoría, luego por el nombre.
        //    normalizePlatform() funciona sobre cualquier string que contenga el
        //    token (ej. "Dead Space - STEAM" -> STEAM), así que sirve de fallback.
        $platform    = null;
        $rawPlatform = null;
        foreach ($data['categories'] ?? [] as $cat) {
            if ($p = WooProduct::normalizePlatform($cat)) {
                $platform    = $p;
                $rawPlatform = $cat;
                break;
            }
        }
        if (! $platform) {
            $platform = WooProduct::normalizePlatform($data['name']);
        }

        // 4) Resolver el juego.
        //    CLAVE: si el producto YA existe y está vinculado a un juego, respetamos
        //    ese vínculo y NO lo re-resolvemos por nombre. De lo contrario, cada update
        //    de Woo (precio/stock/imagen) recalcularía el slug y podría mover el
        //    producto a otro juego, dejando huérfanas las cuentas que lo apuntaban.
        //    Solo resolvemos/creamos juego para productos nuevos o sin vincular.
        $existing  = WooProduct::find($data['id']);
        $canonical = Game::stripPlatform($data['name']);

        if ($existing && $existing->game_id) {
            $gameId = $existing->game_id;

            // Mantener el NOMBRE del juego en sincronía con el del producto sin
            // tocar slug ni game_id (los vínculos con cuentas/productos siguen
            // intactos). Es necesario: AccountDeliveryMail decide la entrega en
            // preorden por el texto del nombre (canonical_name), así que cuando en
            // Woo le quitan el "PRE ORDEN" al producto, el juego DEBE perderlo
            // también o las entregas normales saldrían sin credenciales.
            $game = Game::find($gameId);
            if ($game && $canonical !== '' && $game->canonical_name !== $canonical) {
                $game->update([
                    'canonical_name'  => $canonical,
                    'normalized_name' => Str::lower($canonical),
                ]);
            }
        } else {
            $slug = Str::slug($canonical);

            $game = Game::firstOrCreate(
                ['slug' => $slug],
                [
                    'canonical_name'  => $canonical,
                    'normalized_name' => Str::lower($canonical),
                ]
            );
            $gameId = $game->id;
        }

        // 5) Upsert del producto. El id viene de WooCommerce (no auto-increment).
        //    Nunca tocamos game_id de un producto ya vinculado (ver punto 4).
        $product = WooProduct::updateOrCreate(
            ['id' => $data['id']],
            [
                'game_id'        => $gameId,
                'name'           => $data['name'],
                'platform'       => $platform,
                'image_url'      => $data['image_url'] ?? null,
                'category_raw'   => $rawPlatform
                    ?? (! empty($data['categories']) ? implode(', ', $data['categories']) : null),
                'last_synced_at' => now(),
            ]
        );

        return response()->json([
            'ok'         => true,
            'product_id' => $product->id,
            'game_id'    => $gameId,
            'platform'   => $platform,
        ]);
    }
}
