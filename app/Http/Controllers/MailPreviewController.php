<?php

namespace App\Http\Controllers;

use App\Mail\AccountDeliveryMail;
use App\Models\Account;
use App\Models\AccountAssignment;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Herramienta interna: probar/enviar las distintas variantes del mail de
 * entrega (AccountDeliveryMail) sin necesidad de una orden real.
 *
 * Construye un OrderItem "de mentira" en memoria (con sus relaciones ya
 * seteadas para no tocar la base) y lo pasa al mismo Mailable de producción,
 * así lo que se ve/envía es EXACTAMENTE lo que recibiría un cliente.
 */
class MailPreviewController extends Controller
{
    /** Variantes de entrega que se pueden probar. */
    private const VARIANTS = [
        'normal'   => 'Entrega normal (con credenciales)',
        'qr'       => 'QR (sin credenciales, sin mensaje de preorden)',
        'preorden' => 'Pre-orden (sin credenciales, con mensaje)',
    ];

    /** Consolas → determinan el template (PS4/PS5 usan el de PlayStation). */
    private const PLATFORMS = [
        'PS5'         => 'PlayStation 5',
        'PS4'         => 'PlayStation 4',
        'XBOX_SERIES' => 'Xbox Series X/S',
        'XBOX_ONE'    => 'Xbox One',
        'SWITCH'      => 'Nintendo Switch',
        'STEAM'       => 'PC / Steam',
    ];

    /** GET /mail-preview */
    public function index()
    {
        return view('mail-preview.index', [
            'variants'  => self::VARIANTS,
            'platforms' => self::PLATFORMS,
        ]);
    }

    /**
     * GET /mail-preview/render  → devuelve el HTML del mail (para el iframe).
     * Renderiza la vista directamente inyectando un $message "falso" cuyo
     * embed() devuelve data-URIs, así las imágenes se ven en el navegador
     * (en el envío real se embeben como cid: normalmente).
     */
    public function preview(Request $request)
    {
        $variant  = $this->normalizeVariant($request->query('variant'));
        $platform = $this->normalizePlatform($request->query('platform'));

        $mailable = new AccountDeliveryMail($this->makeItem($variant, $platform));

        $data = $mailable->data;
        $data['message'] = $this->dataUriEmbedder();

        return response()
            ->view($this->viewFor($data['platform']), $data)
            ->header('Content-Security-Policy', "frame-ancestors 'self'");
    }

    /** POST /mail-preview/send  → envía el mail de prueba al correo indicado. */
    public function send(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email|max:255',
            'variant'  => ['required', Rule::in(array_keys(self::VARIANTS))],
            'platform' => ['required', Rule::in(array_keys(self::PLATFORMS))],
        ]);

        try {
            $mailable = new AccountDeliveryMail(
                $this->makeItem($validated['variant'], $validated['platform'])
            );

            // sendNow: envía sincrónico aunque el Mailable sea ShouldQueue,
            // así el operador tiene feedback inmediato (no depende del worker).
            Mail::to($validated['email'])->sendNow($mailable);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'email' => 'No se pudo enviar: ' . $e->getMessage(),
            ]);
        }

        $variantLabel = self::VARIANTS[$validated['variant']];

        return back()->with(
            'success',
            "Mail de prueba enviado a {$validated['email']} · {$validated['platform']} · {$variantLabel}"
        );
    }

    /**
     * Arma un OrderItem sintético (sin persistir) con todas sus relaciones ya
     * cargadas, para que el Mailable no dispare ninguna query.
     */
    private function makeItem(string $variant, string $platform): OrderItem
    {
        $order = new Order();
        $order->customer_name = 'Cliente de Prueba';
        $order->wc_order_id   = '99999';

        $account = new Account();
        $account->email    = 'cuenta.demo@playxdigital.com';
        $account->password = 'Demo-Pass-1234';
        $account->platform = $platform;

        $assignment = new AccountAssignment();
        $assignment->key_value = 'DEMO-KEY-1234-5678';

        $item = new OrderItem();
        $item->game_title        = $this->sampleTitle($platform);
        $item->console_model_raw = $platform;
        $item->is_preorden       = $variant === 'preorden';
        $item->is_pack           = false;
        $item->qr                = $variant === 'qr' ? 'si' : null;

        // Relaciones precargadas → loadMissing() dentro del Mailable no consulta.
        $item->setRelation('order', $order);
        $item->setRelation('game', null);
        $item->setRelation('account', $account);
        $item->setRelation('assignment', $assignment);

        return $item;
    }

    /** Título de ejemplo por consola (solo para la prueba). */
    private function sampleTitle(string $platform): string
    {
        return match ($platform) {
            'PS5', 'PS4'               => 'Grand Theft Auto VI',
            'XBOX_SERIES', 'XBOX_ONE'  => 'Halo Infinite',
            'SWITCH'                   => 'The Legend of Zelda',
            'STEAM'                    => 'Cyberpunk 2077',
            default                    => 'Juego de Prueba',
        };
    }

    /** Mismo criterio que AccountDeliveryMail::content() para elegir template. */
    private function viewFor(string $generalPlatform): string
    {
        return $generalPlatform === 'playstation'
            ? 'emails.account-delivery-playstation'
            : 'emails.account-delivery';
    }

    private function normalizeVariant(?string $variant): string
    {
        return array_key_exists($variant, self::VARIANTS) ? $variant : 'normal';
    }

    private function normalizePlatform(?string $platform): string
    {
        return array_key_exists($platform, self::PLATFORMS) ? $platform : 'PS5';
    }

    /**
     * $message "falso" para el preview en navegador: embed() de un archivo
     * devuelve un data-URI en vez de un cid:, así la imagen se ve en el iframe.
     */
    private function dataUriEmbedder(): object
    {
        return new class {
            public function embed($path): string
            {
                if (! is_file($path)) {
                    return '';
                }
                return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
            }
        };
    }
}
