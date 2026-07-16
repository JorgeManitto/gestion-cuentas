<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;">
  <div style="max-width:620px;margin:0 auto;padding:20px;">

    <div style="background:linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%);color:#fff;padding:24px;text-align:center;border-radius:12px 12px 0 0;">
      <h1 style="margin:0;font-size:22px;">Tu cuenta para {{ $gameTitle }}</h1>
      <div style="font-size:14px;opacity:0.9;">Hola {{ $customerName }}, a continuación te compartimos tus licencias.</div>
    </div>

    <div style="background:#f9fafb;padding:24px;border-radius:0 0 12px 12px;">

      @if ($isPreorden)
        {{-- ────── PRE-ORDEN: reserva confirmada, sin credenciales ────── --}}
        <div style="background:#fff;border:1px solid #e5e7eb;border-left:4px solid #6366f1;border-radius:12px;padding:16px;margin:16px 0;">
          <h3 style="margin:0 0 8px 0;font-size:16px;color:#111827;">🕒 Tu reserva está confirmada</h3>
          <p style="margin:0;color:#374151;font-size:14px;">
            <strong>{{ $gameTitle }}</strong> es un juego en <strong>PRE-ORDEN</strong>, así que todavía no está disponible para descargar.
            El día del lanzamiento podrá hacerlo. 
          </p>
          <p style="margin:10px 0 0 0;color:#374151;font-size:14px;">
            A continuación siga los pasos de activación de la cuenta👇
          </p>
        </div>
      @else
        @if ($showCredentials || $activationKey)
          <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:16px 0;">
            <h3 style="margin:0 0 12px 0;font-size:16px;color:#111827;">LICENCIAS PARA DESCARGAR TU JUEGO</h3>

            @if ($showCredentials)
              <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:8px;background:#f3f4f6;margin:8px 0;">
                <span style="font-weight:600;color:#111827;">Correo:</span>
                <span style="font-family:monospace;color:#111827;font-weight:600;">{{ $accountEmail }}</span>
              </div>
              <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:8px;background:#f3f4f6;margin:8px 0;">
                <span style="font-weight:600;color:#111827;">Contraseña:</span>
                <span style="font-family:monospace;color:#111827;font-weight:600;">{{ $accountPass }}</span>
              </div>

              {{-- Aviso de seguridad de la cuenta --}}
              <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px 14px;margin:12px 0 4px 0;color:#7c2d12;font-size:13px;line-height:1.5;">
                🛡️ <strong>Importante:</strong> No modifique el correo, contraseña, código ni la verificación en dos pasos (2FA). Compartir la cuenta o usarla en varias consolas ocasionará la pérdida del acceso y de la garantía de PlayXDigital.
              </div>
            @endif

            @if ($activationKey)
              <div style="display:flex;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:8px;background:#f3f4f6;margin:8px 0;">
                <span style="font-weight:600;color:#111827;">Código:</span>
                <span style="font-family:monospace;color:#111827;font-weight:600;">{{ $activationKey }}</span>
              </div>
            @endif
          </div>
        @endif
      @endif

      <table role="presentation" align="center" cellspacing="0" cellpadding="0" style="margin:16px 0;">
        <tr>
          <td style="padding:0 6px;">
            <a href="{{ $tutorialUrl }}" target="_blank" rel="noopener"
               style="display:inline-block;background:#0ea5e9;color:#fff;text-decoration:none;font-weight:700;padding:12px 16px;border-radius:8px;">Ver video tutorial</a>
          </td>
          @if ($supportUrl)
          <td style="padding:0 6px;">
            <a href="{{ $supportUrl }}" target="_blank" rel="noopener"
               style="display:inline-block;background:#111827;color:#fff;text-decoration:none;font-weight:700;padding:12px 16px;border-radius:8px;">Canal de soporte</a>
          </td>
          @endif
        </tr>
      </table>

      <p>Si tienes alguna pregunta o problema, no dudes en contactarnos.</p>
      <p>¡Disfruta tu juego! 🎮</p>

      <div style="margin-top:16px;color:#6b7280;font-size:13px;">
        <p>Saludos,<br><strong>El equipo de PlayXDigital</strong><br>
        <small>{{ $platform }}</small></p>
      </div>
    </div>
  </div>
</body>
</html>