<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light only">
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#333;">

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;">
    <tr>
      <td align="center">

        <table role="presentation" width="620" cellpadding="0" cellspacing="0"
               style="width:100%;max-width:620px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08);">

          {{-- ══════════════ HEADER (PlayStation) ══════════════ --}}
          <tr>
            <td style="background:#0b0b12;padding:22px 28px 24px 28px;">

              {{-- Marca (logo) + logo PlayStation --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="vertical-align:middle;">
                    <img src="{{ $message->embed(public_path('emails/logo.png')) }}"
                         width="72" alt="PlayXDigital" style="display:block;border:0;outline:none;">
                  </td>
                  <td align="right" style="vertical-align:middle;">
                    <img src="{{ $message->embed(public_path('emails/logo-playstation.png')) }}"
                         width="64" alt="PlayStation" style="display:block;border:0;outline:none;">
                  </td>
                </tr>
              </table>

              {{-- Título del pedido --}}
              <div style="margin-top:18px;">
                <span style="font-size:22px;font-weight:800;color:#ffffff;line-height:1.3;">Tu cuenta para {{ $gameTitle }}</span>
                @if ($isPreorden)
                  <span style="display:inline-block;background:#facc15;color:#111827;font-weight:800;font-size:12px;padding:4px 10px;border-radius:6px;margin-left:6px;vertical-align:middle;">PRE ORDEN</span>
                @endif
              </div>

              {{-- Saludo --}}
              <div style="margin-top:6px;">
                <span style="font-size:14px;color:#cbd5e1;">Hola {{ $customerName }}, a continuación te compartimos tus licencias.</span>
              </div>

            </td>
          </tr>

          {{-- ══════════════ CUERPO ══════════════ --}}
          <tr>
            <td style="background:#f9fafb;padding:24px 28px;">

              {{-- ══ Fila flex: icon-check (izq.) + mensaje según variante (der.) ══ --}}
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  {{-- Ícono de confirmación --}}
                  <td width="112" valign="top" style="padding:4px 18px 0 0;">
                    <img src="{{ $message->embed(public_path('emails/icon-check.png')) }}"
                         width="96" alt="" style="display:block;border:0;outline:none;">
                  </td>

                  {{-- Mensaje --}}
                  <td valign="top">

                    @if ($isPreorden)
                      {{-- ────── PRE-ORDEN: reserva confirmada, sin credenciales ────── --}}
                      <h2 style="margin:0 0 6px 0;font-size:20px;font-weight:800;color:#111827;">🕒 Tu reserva está confirmada</h2>
                      <div style="width:44px;height:3px;background:#f97316;border-radius:2px;margin:0 0 12px 0;"></div>
                      <p style="margin:0 0 12px 0;color:#374151;font-size:14px;">
                        <strong>{{ $gameTitle }}</strong> es un juego en <strong style="color:#f97316;">PRE-ORDEN</strong>, así que todavía no está disponible para descargar.
                        El día del lanzamiento podrá hacerlo.
                      </p>
                      <p style="margin:0 0 4px 0;color:#374151;font-size:14px;">
                        A continuación siga los pasos de activación de la cuenta👇
                      </p>

                    @elseif ($isQr)
                      {{-- ────── QR: agradecimiento, sin credenciales ────── --}}
                      <h2 style="margin:0 0 6px 0;font-size:20px;font-weight:800;color:#111827;">🎉 ¡Gracias por tu compra!</h2>
                      <div style="width:44px;height:3px;background:#f97316;border-radius:2px;margin:0 0 12px 0;"></div>
                      <p style="margin:0 0 12px 0;color:#374151;font-size:14px;">
                        Para completar la instalación de tu juego, te invitamos a seguir paso a paso las instrucciones del <strong style="color:#f97316;">video tutorial</strong>.
                      </p>
                      <hr style="border:0;border-top:1px solid #e5e7eb;margin:12px 0;">
                      <p style="margin:0 0 14px 0;color:#374151;font-size:14px;">
                        Una vez finalizado el procedimiento, <strong style="color:#f97316;">comunícate con nuestro equipo de soporte</strong> para realizar la activación de tu juego y asegurarnos de que todo quede correctamente configurado.
                      </p>

                      {{-- Aviso informativo --}}
                      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                             style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;">
                        <tr>
                          <td width="34" valign="top" style="padding:12px 0 12px 12px;">
                            <img src="{{ $message->embed(public_path('emails/icon-warning.png')) }}"
                                 width="22" alt="" style="display:block;border:0;outline:none;">
                          </td>
                          <td style="padding:12px;color:#7c2d12;font-size:13px;">
                            A continuación encontrarás el <strong style="color:#f97316;">video tutorial</strong> y nuestro <strong style="color:#f97316;">canal de soporte</strong> para ayudarte en cada paso del proceso.
                          </td>
                        </tr>
                      </table>

                    @else
                      {{-- ────── VENTA NORMAL: credenciales / código ────── --}}
                      <h2 style="margin:0 0 6px 0;font-size:20px;font-weight:800;color:#111827;">🎮 ¡Gracias por tu compra!</h2>
                      <div style="width:44px;height:3px;background:#f97316;border-radius:2px;margin:0 0 12px 0;"></div>

                      @if ($showCredentials || $activationKey)
                        <h3 style="margin:0 0 10px 0;font-size:14px;font-weight:700;color:#111827;">LICENCIAS PARA DESCARGAR TU JUEGO</h3>

                        @if ($showCredentials)
                          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0;background:#f3f4f6;border-radius:8px;">
                            <tr>
                              <td style="padding:10px 12px;font-weight:600;color:#111827;font-size:14px;">Correo:</td>
                              <td align="right" style="padding:10px 12px;font-family:'Courier New',monospace;color:#111827;font-weight:600;font-size:14px;">{{ $accountEmail }}</td>
                            </tr>
                          </table>
                          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0;background:#f3f4f6;border-radius:8px;">
                            <tr>
                              <td style="padding:10px 12px;font-weight:600;color:#111827;font-size:14px;">Contraseña:</td>
                              <td align="right" style="padding:10px 12px;font-family:'Courier New',monospace;color:#111827;font-weight:600;font-size:14px;">{{ $accountPass }}</td>
                            </tr>
                          </table>
                        @endif

                        @if ($activationKey)
                          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0;background:#f3f4f6;border-radius:8px;">
                            <tr>
                              <td style="padding:10px 12px;font-weight:600;color:#111827;font-size:14px;">Código:</td>
                              <td align="right" style="padding:10px 12px;font-family:'Courier New',monospace;color:#111827;font-weight:600;font-size:14px;">{{ $activationKey }}</td>
                            </tr>
                          </table>
                        @endif
                      @endif
                    @endif

                  </td>
                </tr>
              </table>

              {{-- ────── Botones: tutorial + soporte ────── --}}
              <table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:20px auto 8px auto;">
                <tr>
                  <td style="padding:0 6px;">
                    <a href="{{ $tutorialUrl }}" target="_blank" rel="noopener"
                       style="display:inline-block;background:#f97316;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:8px;font-size:14px;">
                      <img src="{{ $message->embed(public_path('emails/icon-tutorial.png')) }}" width="16" alt="" style="vertical-align:middle;margin-right:8px;border:0;">Ver video tutorial
                    </a>
                  </td>
                  @if ($supportUrl)
                  <td style="padding:0 6px;">
                    <a href="{{ $supportUrl }}" target="_blank" rel="noopener"
                       style="display:inline-block;background:#111827;color:#ffffff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:8px;font-size:14px;">
                      <img src="{{ $message->embed(public_path('emails/icon-support.png')) }}" width="16" alt="" style="vertical-align:middle;margin-right:8px;border:0;">Canal de soporte
                    </a>
                  </td>
                  @endif
                </tr>
              </table>

              {{-- ────── Pie ────── --}}
              <p style="margin:18px 0 6px 0;color:#374151;font-size:14px;">Si tienes alguna pregunta o problema, no dudes en contactarnos.</p>
              <p style="margin:0 0 16px 0;color:#374151;font-size:14px;">¡Disfruta tu juego! 🎮</p>

              <div style="margin-top:16px;color:#6b7280;font-size:13px;">
                <p style="margin:0;">Saludos,<br><strong>El equipo de PlayXDigital</strong><br>
                <small>{{ $platform }}</small></p>
              </div>

            </td>
          </tr>

          {{-- ══════════════ FOOTER (control decorativo) ══════════════ --}}
          <tr>
            <td style="padding-left: 8px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="vertical-align:middle;">
                    <span style="font-size:12px;color:#6b7280;">© PlayXDigital</span>
                  </td>
                  <td align="right" style="vertical-align:middle;">
                    <img src="{{ $message->embed(public_path('emails/bg-joystick.png')) }}"
                         width="120" alt="" style="display:block;border:0;outline:none;">
                  </td>
                </tr>
              </table>
            </td>
          </tr>

        </table>

      </td>
    </tr>
  </table>

</body>
</html>
