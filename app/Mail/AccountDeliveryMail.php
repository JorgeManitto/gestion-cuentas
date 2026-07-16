<?php

namespace App\Mail;

use App\Models\Account;
use App\Models\AccountSecondaryAssignment;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;



class AccountDeliveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Datos ya resueltos para la vista (evita lazy-loading en el render). */
    public array $data;

    /** Tutoriales por consola específica (no por plataforma general). */
    private const TUTORIALS = [
        'PS4'              => 'https://youtu.be/OUiaFjFZC58',
        'PS5'              => 'https://youtu.be/k3zuUDh8rC0',
        'PS5_PREORDEN'     => 'https://youtu.be/3konL3Y1PBs',
        'PS4_QR'           => 'https://youtu.be/3konL3Y1PBs',
        'PS5_QR'           => 'https://youtu.be/3konL3Y1PBs',
        'XBOX_ONE'         => 'https://youtu.be/CL__ScG1AHU',
        'XBOX_SERIES'      => 'https://youtu.be/CL__ScG1AHU',
        'SWITCH'           => 'https://youtu.be/B0L_EFg6UkA',
        'SWITCH_2'         => 'https://youtu.be/B0L_EFg6UkA',
        'STEAM'            => 'https://youtu.be/OUiaFjFZC58',
        'DEFAULT_PREORDEN' => 'https://youtu.be/3konL3Y1PBs',
    ];

    /** Si la consola no tiene tutorial propio, se usa este. */
    private const TUTORIAL_FALLBACK = 'https://youtu.be/OUiaFjFZC58';

    public function __construct(OrderItem $item, ?AccountSecondaryAssignment $secondary = null)
    {
        $item->loadMissing(['order', 'game']);

        if ($secondary) {
            // ── Entrega de PACK: credenciales, llave y JUEGO salen del slot secundario ──
            $secondary->loadMissing('account.game.products');
            $account    = $secondary->account;
            $keyValue   = $secondary->key_value;
            $platform   = $account?->platform;
            // El título del item es el del pack ("3x1 pack..."); el juego real
            // es el de la cuenta secundaria que estamos entregando en este mail.
            $gameTitle  = $this->secondaryGameTitle($account) ?? $this->displayTitle($item);
        } else {
            // ── Entrega normal ──
            $item->loadMissing(['account', 'assignment']);
            $account    = $item->account;
            $keyValue   = $item->assignment?->key_value;
            $platform   = $account?->platform;
            $gameTitle  = $this->displayTitle($item);
        }

        $general    = self::generalPlatform($platform);
        $isPreorden = self::isPreorden($item);
        $isQr       = self::isQr($item);

        // Tanto la preorden como los productos con QR se entregan SIN credenciales.
        // La diferencia: el QR no muestra el mensaje/subject de preorden.
        $hideCredentials = $isPreorden || $isQr;

        $this->data = [
            'customerName'    => $item->order->customer_name,
            'gameTitle'       => $gameTitle,
            'accountEmail'    => $hideCredentials ? null : $account?->email,
            'accountPass'     => $hideCredentials ? null : $account?->password,
            'activationKey'   => $hideCredentials ? null : $keyValue,
            'platform'        => $general,
            'orderId'         => $item->order->wc_order_id,
            'tutorialUrl'     => self::resolveTutorial($platform, $isPreorden, $isQr),
            'supportUrl'      => config('services.delivery_mail.support_url'),
            'isPreorden'      => $isPreorden,
            'isQr'            => $isQr,
            'showCredentials' => ! $hideCredentials && in_array($general, ['playstation', 'nintendo'], true),
        ];
    }

    public function envelope(): Envelope
    {
        $prefix = $this->data['orderId'] ? "[Pedido #{$this->data['orderId']}] " : '';

        $subject = $this->data['isPreorden']
            ? "{$prefix}🕒 Reserva confirmada: {$this->data['gameTitle']}"
            : "{$prefix}🎮 Credenciales de tu cuenta de {$this->data['gameTitle']}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        // PlayStation (PS4/PS5) y Nintendo (Switch) usan su propio template con
        // branding e imágenes. El resto sigue con el genérico.
        $view = match ($this->data['platform']) {
            'playstation' => 'emails.account-delivery-playstation',
            'nintendo'    => 'emails.account-delivery-nintendo',
            default       => 'emails.account-delivery',
        };

        return new Content(
            view: $view,
            with: $this->data,
        );
    }

    /**
     * ¿Es un juego en pre-orden?
     *   - flag is_preorden del item (atributo del producto en Woo), o
     *   - el título contiene "PRE ORDEN" / "PRE-ORDEN" / "PREORDEN".
     */
    private static function isPreorden(OrderItem $item): bool
    {
        if ($item->is_preorden) {
            return true;
        }

        // Comparamos solo letras: "PRE ORDEN", "Pre-Orden", "preorden" → "PREORDEN"
        $haystacks = array_filter([$item->game_title, $item->game?->canonical_name]);

        foreach ($haystacks as $text) {
            $compact = strtoupper(preg_replace('/[^A-Za-z]/', '', $text));
            if (str_contains($compact, 'PREORDEN')) {
                return true;
            }
        }

        return false;
    }

    /**
     * ¿El producto se entrega por QR? Determinado por el atributo "qr" del
     * producto en Woo (persistido en el item). Presencia = producto QR: la
     * entrega omite credenciales pero NO muestra el mensaje de preorden.
     */
    private static function isQr(OrderItem $item): bool
    {
        return filled($item->qr);
    }

    /**
     * Elige el tutorial según la consola específica ($platform: PS4, PS5, …):
     *   - QR:       "{PLATAFORMA}_QR"      (cae a la consola y al fallback general)
     *   - preorden: "{PLATAFORMA}_PREORDEN" (cae a DEFAULT_PREORDEN)
     *   - normal:   "{PLATAFORMA}"          (cae al fallback general)
     */
    private static function resolveTutorial(?string $platform, bool $isPreorden, bool $isQr = false): string
    {
        if ($isQr) {
            return self::TUTORIALS[$platform . '_QR']
                ?? self::TUTORIALS[$platform]
                ?? self::TUTORIAL_FALLBACK;
        }

        if ($isPreorden) {
            return self::TUTORIALS[$platform . '_PREORDEN']
                ?? self::TUTORIALS['DEFAULT_PREORDEN'];
        }

        return self::TUTORIALS[$platform] ?? self::TUTORIAL_FALLBACK;
    }

    /** Mapea nuestra plataforma interna (PS5, XBOX_ONE, SWITCH_2, STEAM…) a la general. */
    private static function generalPlatform(?string $platform): string
    {
        return match ($platform) {
            'PS5', 'PS4'              => 'playstation',
            'XBOX_ONE', 'XBOX_SERIES' => 'xbox',
            'SWITCH', 'SWITCH_2'      => 'nintendo',
            'STEAM'                   => 'pc',
            default                   => 'pc',
        };
    }

    /** Título + modelo de consola si no está ya incluido. */
    private function displayTitle(OrderItem $item): string
    {
        $title = $item->game_title ?: ($item->game?->canonical_name ?? 'tu juego');
        $model = trim((string) $item->console_model_raw);

        if ($model !== '' && stripos($title, $model) === false) {
            $title .= " {$model}";
        }

        return $title;
    }

    /**
     * Título del juego para entregas de PACK (stock secundario).
     * El item apunta al producto del pack ("3x1 pack de juegos"), así que el
     * nombre real del juego lo tomamos de la cuenta secundaria asignada.
     * Preferimos el nombre del producto Woo (suele incluir la plataforma) y
     * caemos al canonical_name del juego si no hay producto resuelto.
     */
    private function secondaryGameTitle(?Account $account): ?string
    {
        if (! $account) {
            return null;
        }

        return $account->coverProduct()?->name
            ?? $account->game?->canonical_name;
    }
}