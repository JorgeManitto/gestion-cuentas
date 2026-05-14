<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'game_id',
        'parent_account_id',
        'platform',
        'account_type',
        'region',
        'email',
        'password',
        'mail_email',
        'mail_password',
        'created_date',
        'purchased_date',
        'reset_date',
        'gamer_tag',
        'birth_date',
        'status',
        'disable_reason',
        'notes',
    ];

    /**
     * Etiquetas humanas para los motivos de deshabilitación.
     * Se usan en las vistas y el modal de deshabilitar.
     */
    public const DISABLE_REASONS = [
        'descanso' => 'En descanso',
        'baneada'  => 'Baneada',
        'robada'   => 'Robada',
        'otro'     => 'Otro motivo',
    ];

    protected $casts = [
        'created_date'   => 'date',
        'purchased_date' => 'date',
        'reset_date'     => 'date',
        'birth_date'     => 'date',
    ];

    // Para que las contraseñas no aparezcan por accidente en respuestas JSON
    protected $hidden = [
        'password',
        'mail_password',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    public function keys(): HasMany
    {
        return $this->hasMany(AccountKey::class)->orderBy('position');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AccountAssignment::class)->orderBy('slot_number');
    }

    // ──────────────────────── CAPACITY ────────────────────────

    /**
     * Capacidad inicial: cuántos slots tiene una cuenta nueva, antes de cualquier reset.
     * Derivada de la plataforma — refleja la realidad de cada ecosistema.
     */
    public function initialCapacity(): int
    {
        return match ($this->platform) {
            'DUAL'                  => 4,   // PS dual: 2 PS4 + 2 PS5
            'PS5', 'PS4'            => 2,
            'XBOX_ONE', 'XBOX_SERIES' => 2,
            'SWITCH', 'SWITCH_2'    => 8,
            'STEAM'                 => 1,
            default                 => 2,
        };
    }

    /**
     * Capacidad post-reset: cuántos slots quedan disponibles después de un reset.
     * - PSN penaliza fuerte: a la mitad
     * - Xbox no penaliza: igual a inicial
     * - Nintendo se desbloquea por tiempo, no por reset count: igual a inicial
     */
    public function maxAfterReset(): int
    {
        return match ($this->platform) {
            'DUAL'                    => 2,                       // 4 → 2
            'PS5', 'PS4'              => 1,                       // 2 → 1
            'XBOX_ONE', 'XBOX_SERIES' => $this->initialCapacity(),
            'SWITCH', 'SWITCH_2'      => $this->initialCapacity(),
            'STEAM'                   => 1,
            default                   => 1,
        };
    }

    /**
     * Capacidad efectiva hoy: depende de si la cuenta fue reseteada.
     * - Si reset_date está set → estamos en régimen post-reset
     * - Si no → régimen inicial
     */
    public function capacity(): int
    {
        return $this->reset_date
            ? $this->maxAfterReset()
            : $this->initialCapacity();
    }

    /**
     * ¿Está reseteada (régimen post-reset activo)?
     */
    public function isPostReset(): bool
    {
        return $this->reset_date !== null;
    }

    /**
     * Slots libres ahora.
     * Tolerante con la relación cargada (evita N+1 si se hizo eager loading).
     */
    public function freeSlots(): int
    {
        $used = $this->relationLoaded('assignments')
            ? $this->assignments->where('status', 'active')->count()
            : $this->assignments()->where('status', 'active')->count();

        return max(0, $this->capacity() - $used);
    }

    // ──────────────────────── REGLA TEMPORAL NINTENDO ────────────────────────

    /**
     * Cantidad de días que Nintendo bloquea una cuenta después del 4° uso.
     * Configurable por si Nintendo cambia la política. Default: 30.
     */
    public const NINTENDO_RESET_DAYS = 30;

    /**
     * ¿La cuenta está bloqueada por la regla temporal de Nintendo?
     *
     * Lógica: si una cuenta Switch llegó a 4+ activaciones, queda bloqueada
     * durante NINTENDO_RESET_DAYS contados desde la 4° asignación.
     * Si no encontramos fecha de la 4° (típicamente porque son placeholders
     * sin assigned_at), usamos purchased_date como fallback.
     */
    public function isTimeBlocked(): bool
    {
        return $this->timeBlockUnlockAt() !== null
            && $this->timeBlockUnlockAt()->isFuture();
    }

    /**
     * Fecha en que la cuenta se desbloquea, o null si no está sujeta a la regla.
     */
    public function timeBlockUnlockAt(): ?\Carbon\Carbon
    {
        if (! in_array($this->platform, ['SWITCH', 'SWITCH_2'])) {
            return null;
        }

        $active = $this->relationLoaded('assignments')
            ? $this->assignments->where('status', 'active')
            : $this->assignments()->where('status', 'active')->get();

        if ($active->count() < 4) {
            return null;
        }

        // Buscar el 4° por orden de assigned_at (ignorando los placeholders sin fecha)
        $fourth = $active
            ->whereNotNull('assigned_at')
            ->sortBy('assigned_at')
            ->values()
            ->get(3);

        $reference = $fourth?->assigned_at ?? $this->purchased_date;
        if (! $reference) {
            return null;
        }

        return \Carbon\Carbon::parse($reference)->addDays(self::NINTENDO_RESET_DAYS);
    }

    // ──────────────────────── DESHABILITADA ────────────────────────

    /**
     * ¿La cuenta está deshabilitada por el operador?
     * Cualquier status != active cuenta como deshabilitada operativamente.
     */
    public function isDisabled(): bool
    {
        return $this->status !== 'active';
    }

    /**
     * Etiqueta humana del motivo de deshabilitación.
     */
    public function disableReasonLabel(): ?string
    {
        return $this->disable_reason
            ? (self::DISABLE_REASONS[$this->disable_reason] ?? $this->disable_reason)
            : null;
    }
}
