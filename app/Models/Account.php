<?php

namespace App\Models;

use App\Models\Concerns\HasSecondaryStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Account extends Model
{
    use SoftDeletes;
    use HasSecondaryStock;

    protected $fillable = [
        'game_id',
        'parent_account_id',
        'platform',
        'type_console',
        'is_dual',
        'account_type',
        'region',
        'email',
        'password',
        'mail_email',
        'mail_password',
        'created_date',
        'purchased_date',
        'reset_date',
        'reset_snooze_until',
        'gamer_tag',
        'full_name',
        'birth_date',
        'status',
        'disable_reason',
        'notes',
        'is_membership',
        'membership_duration_months', 
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
        'is_dual'        => 'boolean',
        'is_membership'  => 'boolean',
        'created_date'       => 'date',
        'purchased_date'     => 'date',
        'reset_date'         => 'date',
        'reset_snooze_until' => 'date',
        'birth_date'         => 'date',
    ];

    /** Duraciones de membresía válidas, en meses. */
    public const MEMBERSHIP_DURATIONS = [3, 6, 12];

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
        return collect($this->coveredPlatforms())
            ->sum(fn ($p) => $this->initialCapacityPerPlatform($p));
    }

    /**
     * Capacidad post-reset: cuántos slots quedan disponibles después de un reset.
     * - PSN penaliza fuerte: a la mitad
     * - Xbox no penaliza: igual a inicial
     * - Nintendo penaliza: 8 → 4 (tras el reset la cuenta queda con una sola tanda)
     */
    public function maxAfterReset(): int
    {
        return collect($this->coveredPlatforms())
            ->sum(fn ($p) => $this->maxAfterResetPerPlatform($p));
    }

    /**
     * Capacidad efectiva hoy: depende de si la cuenta fue reseteada.
     * - Si reset_date está set → estamos en régimen post-reset
     * - Si no → régimen inicial
     */
    public function capacity(): int
    {
        return collect($this->coveredPlatforms())
            ->sum(fn ($p) => $this->capacityFor($p));
    }

    public function freeSlots(): int
    {
        return collect($this->coveredPlatforms())
            ->sum(fn ($p) => $this->freeSlotsFor($p));
    }

    /**
     * ¿La cuenta ya fue efectivamente reseteada?
     *
     * `reset_date` guarda la fecha del PRÓXIMO reset (viene de `next_reset_date` en el
     * import de Nintendo). Mientras esa fecha sea futura la cuenta TODAVÍA no se reseteó
     * y conserva su capacidad inicial (8 usos). Recién cuando la fecha llega —queda en el
     * pasado— pasa al régimen reducido (4 usos).
     */
    public function hasBeenReset(): bool
    {
        return $this->reset_date !== null && ! $this->reset_date->isFuture();
    }

    /**
     * ¿Está reseteada (régimen post-reset activo)?
     */
    public function isPostReset(): bool
    {
        return $this->hasBeenReset();
    }

    /** Qué slot-platform consume este pedido en esta cuenta. */
    public function resolveSlotPlatform(string $requestedPlatform): ?string
    {
        $covered = $this->coveredPlatforms();

        if (in_array($requestedPlatform, $covered, true)) {
            return $requestedPlatform;          // caso normal (PS4 → PS4)
        }

        // cross-compat: cuenta no-dual que cubre una sola plataforma
        return count($covered) === 1 ? $covered[0] : null;
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
    /** Las sub-plataformas físicas que esta cuenta cubre. */
    public function coveredPlatforms(): array
    {
        if (! $this->is_dual) {
            return [$this->platform];
        }

        return match (true) {
            in_array($this->platform, ['PS4', 'PS5'], true)              => ['PS4', 'PS5'],
            in_array($this->platform, ['XBOX_ONE', 'XBOX_SERIES'], true) => ['XBOX_ONE', 'XBOX_SERIES'],
            in_array($this->platform, ['SWITCH', 'SWITCH_2'], true)      => ['SWITCH', 'SWITCH_2'],
            default                                                      => [$this->platform],
        };
    }

    private function initialCapacityPerPlatform(string $platform): int
    {
        // Nintendo dual: el total de 8 usos se reparte 4 + 4 entre Switch 1 y Switch 2.
        // Exclusiva (una sola plataforma) conserva los 8 usos completos.
        if ($this->is_dual && in_array($platform, ['SWITCH', 'SWITCH_2'], true)) {
            return 4;
        }

        return match ($platform) {
            'PS5', 'PS4'              => 2,
            'XBOX_ONE', 'XBOX_SERIES' => 2,
            'SWITCH', 'SWITCH_2'      => 8,   // Nintendo exclusiva: 8 usos
            'STEAM'                   => 1,
            default                   => 2,
        };
    }

    private function maxAfterResetPerPlatform(string $platform): int
    {
        // Nintendo dual post-reset: el total de 4 usos se reparte 2 + 2 (2 Switch 1 + 2 Switch 2).
        if ($this->is_dual && in_array($platform, ['SWITCH', 'SWITCH_2'], true)) {
            return 2;
        }

        return match ($platform) {
            'PS5', 'PS4'              => 1,   // PSN penaliza: 2 → 1
            'XBOX_ONE', 'XBOX_SERIES' => 2,   // Xbox no penaliza
            'SWITCH', 'SWITCH_2'      => 4,   // Nintendo exclusiva penaliza: 8 → 4
            'STEAM'                   => 1,
            default                   => 1,
        };
    }

    /** Budget de UNA plataforma concreta (0 si la cuenta no la cubre). */
    public function capacityFor(string $platform): int
    {
        if (! in_array($platform, $this->coveredPlatforms(), true)) {
            return 0;
        }

        return $this->hasBeenReset()
            ? $this->maxAfterResetPerPlatform($platform)
            : $this->initialCapacityPerPlatform($platform);
    }

    /** Cupos libres en UNA plataforma concreta. */
    public function freeSlotsFor(string $platform): int
    {
        $active = $this->relationLoaded('assignments')
            ? $this->assignments->where('status', 'active')
            : $this->assignments()->where('status', 'active')->get();

        $used = $active->where('platform', $platform)->count();

        return max(0, $this->capacityFor($platform) - $used);
    }
    /**
     * ¿Esta cuenta permite elegir sub-plataforma al agregar/quitar usos manuales?
     * Por ahora SOLO PlayStation dual (cubre PS4 y PS5). Switch/Xbox/Steam usan el botón único.
     */
    public function canPickUsagePlatform(): bool
    {
        $covered = $this->coveredPlatforms();

        return count($covered) > 1
            && collect($covered)->every(fn ($p) => in_array($p, ['PS4', 'PS5'], true));
    }
    /**
     * Resuelve el producto Woo que matchea la plataforma de la cuenta.
     * Primero intenta el match directo (productForPlatform) y, si falla,
     * normaliza la plataforma (saca espacios/guiones y mapea Xbox Series X|S)
     * y busca por esa columna.
     *
     * Requiere que `game.products` esté eager-loaded para no disparar queries extra.
     */
    public function coverProduct()
    {
        if (! $this->game) {
            return null;
        }

        $normalized = str_replace([' ', '-'], '', strtolower($this->platform));
        
        if ($normalized === 'xboxseriesx|s') {
            $normalized = 'xboxseries';
        }
        $pivot = $this->game->products->first(
            fn ($product) => str_replace([' ', '-'], '', strtolower($product->platform)) === $normalized
        );

        return $this->game->productForPlatform($this->platform)
            ?? $pivot;
    }
    // ──────────────────────── MEMBRESÍA: VENTANA DE VENTA ────────────────────────

    /**
     * Meses transcurridos desde el inicio a partir de los cuales se DEJAN de
     * vender cupos nuevos, según la duración de la membresía.
     *
     *   - 3 meses  → se bloquea al entrar en el último mes (a los 2 meses).
     *   - 12 meses → se bloquea pasados 3 meses desde el inicio.
     *   - 6 meses  → ASUMIDO 4 meses (no estaba en la spec, CONFIRMAR con negocio).
     *
     * Si una duración no figura acá, no se aplica restricción temporal.
     */
    public const MEMBERSHIP_SELL_CUTOFF_MONTHS = [
        3  => 2,
        6  => 4,   // ← asumido, confirmar
        12 => 3,
    ];

    /**
     * Inicio de la membresía: la PRIMERA asignación con fecha (assigned_at más
     * antiguo). El reloj arranca cuando se vendió el primer cupo.
     * Ignora placeholders sin assigned_at. Null si todavía no se vendió nada
     * (cuenta nueva sin uso → se puede vender).
     */
    public function membershipStartDate(): ?\Carbon\Carbon
    {
        if (! $this->is_membership) {
            return null;
        }

        // Respeta el eager-load de findCandidates / loadMissing de assign
        $assignments = $this->relationLoaded('assignments')
            ? $this->assignments
            : $this->assignments()->get();

        $first = $assignments
            ->whereNotNull('assigned_at')
            ->sortBy('assigned_at')
            ->first();

        return $first?->assigned_at;   // ya es Carbon por el cast 'date' del modelo
    }

    /** Fecha de vencimiento de la membresía (inicio + duración). */
    public function membershipExpiresAt(): ?\Carbon\Carbon
    {
        $start = $this->membershipStartDate();
        if (! $start || ! $this->membership_duration_months) {
            return null;
        }
        return $start->copy()->addMonths($this->membership_duration_months);
    }

    /** Fecha a partir de la cual NO se venden cupos nuevos (inicio + cutoff). */
    public function membershipSaleClosesAt(): ?\Carbon\Carbon
    {
        $start = $this->membershipStartDate();
        if (! $start || ! $this->membership_duration_months) {
            return null;
        }
        $cutoff = self::MEMBERSHIP_SELL_CUTOFF_MONTHS[$this->membership_duration_months] ?? null;
        if ($cutoff === null) {
            return null;   // duración sin regla → sin restricción temporal
        }
        return $start->copy()->addMonths($cutoff);
    }

    /** ¿Ya venció la membresía? */
    public function isMembershipExpired(): bool
    {
        $expiresAt = $this->membershipExpiresAt();
        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * ¿Está cerrada la ventana de venta de cupos nuevos?
     * Para cuentas que NO son membresía siempre devuelve false (sin restricción).
     */
    public function isMembershipSaleClosed(): bool
    {
        if (! $this->is_membership || ! $this->isPlayStation()) {
            return false;
        }
        if ($this->isMembershipExpired()) {
            return true;   // vencida → no se vende ("no vender cupos vencidos")
        }
        $closesAt = $this->membershipSaleClosesAt();
        return $closesAt !== null && ! $closesAt->isFuture();
    }

    /** ¿Es una cuenta PlayStation (PS4/PS5, incl. dual)? */
    public function isPlayStation(): bool
    {
        return collect($this->coveredPlatforms())
            ->every(fn ($p) => in_array($p, ['PS4', 'PS5'], true));
    }

    // ──────────────────────── STOCK RESETEABLE ────────────────────────

    /** Meses desde la última asignación a partir de los cuales la cuenta es reseteable. */
    public const RESET_ELIGIBLE_MONTHS = 6;

    /** Fecha de la última asignación ACTIVA con fecha (la más reciente). Null si no hay. */
    public function lastAssignmentDate(): ?\Carbon\Carbon
    {
        $assignments = $this->relationLoaded('assignments')
            ? $this->assignments
            : $this->assignments()->get();

        return $assignments
            ->where('status', 'active')
            ->filter(fn ($a) => filled($a->assigned_at))
            ->map(fn ($a) => \Carbon\Carbon::parse($a->assigned_at))
            ->max();   // Carbon es comparable → devuelve la más reciente
    }

    /** Fecha a partir de la cual la cuenta queda elegible (reset o compra + N meses). */
    public function resetEligibleAt(): ?\Carbon\Carbon
    {
        return $this->stockRotationReference()?->copy()->addMonths(self::RESET_ELIGIBLE_MONTHS);
    }

    /**
     * ¿Es "stock reseteable"?
     *   - No está en snooze/prórroga
     *   - Todos los slots ocupados (sin cupos libres)
     *   - Pasaron RESET_ELIGIBLE_MONTHS desde su última asignación activa
     *   - Pasaron RESET_ELIGIBLE_MONTHS desde la referencia de rotación (reset_date ?? purchased_date)
     */
    public function isResettableStock(?string $platform = null): bool
    {
        // Prórroga vigente: el operador la pospuso, no la recomendamos todavía.
        if ($this->isResetSnoozed()) {
            return false;
        }

        // Si nos pasan la plataforma, evaluamos SOLO ese pool; si no, el total (comportamiento viejo).
        $freeInPool = $platform !== null
            ? $this->freeSlotsFor($platform)
            : $this->freeSlots();

        if ($freeInPool > 0) {
            return false;   // todavía hay cupos para vender en ese pool
        }

        // No recomendar si el último envío es reciente: deben pasar
        // RESET_ELIGIBLE_MONTHS desde la última asignación activa.
        $lastAssignment = $this->lastAssignmentDate();
        if ($lastAssignment !== null
            && $lastAssignment->copy()->addMonths(self::RESET_ELIGIBLE_MONTHS)->isFuture()) {
            return false;
        }

        $eligibleAt = $this->resetEligibleAt();

        return $eligibleAt !== null && ! $eligibleAt->isFuture();
    }

    /**
     * Pre-filtro a nivel SQL para acotar candidatos antes del chequeo de capacidad (que es PHP):
     *   - status activo
     *   - tiene al menos una asignación activa CON fecha
     *   - NO tiene ninguna asignación activa más nueva que el cutoff
     *     → la más reciente cae <= cutoff
     */
    public function scopeResettableCandidates($query)
    {
        $cutoff = now()->subMonths(self::RESET_ELIGIBLE_MONTHS);

        return $query
            ->where('status', 'active')
            ->whereRaw('COALESCE(reset_date, purchased_date) IS NOT NULL')
            ->whereRaw('COALESCE(reset_date, purchased_date) <= ?', [$cutoff])
            // Snooze/prórroga: excluye las pospuestas cuyo plazo todavía no venció.
            ->where(fn ($q) => $q
                ->whereNull('reset_snooze_until')
                ->orWhere('reset_snooze_until', '<=', now()));
    }
    /**
     * Fecha de referencia para la rotación de stock:
     *   - último reset (reset_date) si la cuenta ya fue reseteada
     *   - si nunca fue reseteada → fecha de compra (purchased_date)
     * Null si no hay ninguna de las dos.
     */
    public function stockRotationReference(): ?\Carbon\Carbon
    {
        $ref = $this->reset_date ?? $this->purchased_date;

        return $ref ? \Carbon\Carbon::parse($ref) : null;
    }

    /** ¿La referencia de antigüedad viene de un reset o de la compra? */
    public function stockRotationSource(): ?string
    {
        if ($this->reset_date)     return 'reset';
        if ($this->purchased_date) return 'compra';
        return null;
    }

    // ──────────────────────── SNOOZE / PRÓRROGA DE RESET ────────────────────────

    /**
     * ¿La cuenta está pospuesta (snooze vigente)?
     * Mientras esté vigente NO aparece en los recomendados a resetear, aunque ya
     * cumpla la ventana real de 6 meses. Vencido el plazo, vuelven las reglas normales.
     */
    public function isResetSnoozed(): bool
    {
        return $this->reset_snooze_until !== null
            && $this->reset_snooze_until->isFuture();
    }

    /** Meses (redondeados hacia arriba) que faltan para que se vuelva a recomendar. Null si no está pospuesta. */
    public function resetSnoozeMonthsLeft(): ?int
    {
        if (! $this->isResetSnoozed()) {
            return null;
        }

        return (int) ceil(now()->diffInDays($this->reset_snooze_until, false) / 30);
    }

    /** Antigüedad en días desde la referencia de rotación. Más alto = más prioritario. */
    public function stockRotationAgeInDays(): ?int
    {
        return $this->stockRotationReference()?->diffInDays(now());
    }
    public function madre()
    {
        return $this->belongsTo(Account::class, 'parent_account_id');
    }

    public function hijas()
    {
        return $this->hasMany(Account::class, 'parent_account_id');
    }
    /** Primera llave sin usar (por position). Null si no quedan. */
    public function nextAvailableKey(): ?AccountKey
    {
        $keys = $this->relationLoaded('keys') ? $this->keys : $this->keys()->get();

        return $keys->first(fn ($k) => is_null($k->used_at));
    }

    /** Días que deben pasar entre un reset y el siguiente. */
    const RESET_COOLDOWN_DAYS = 181;
    /**
     * Fecha base para calcular el cooldown:
     *   - usa reset_date si la cuenta ya se reseteó
     *   - si no, cae a purchased_date
     *   - null si no hay ninguna
     */
    public function resetCooldownBaseDate(): ?Carbon
    {
        return $this->reset_date ?? $this->purchased_date;
    }

    /** Fecha en la que se vuelve a habilitar el reset (null si no hay fecha base). */
    public function resetCooldownUntil(): ?Carbon
    {
        return $this->resetCooldownBaseDate()?->copy()->addDays(self::RESET_COOLDOWN_DAYS);
    }

    /** ¿Está dentro de la ventana de bloqueo de 180 días? */
    public function isResetOnCooldown(): bool
    {
        return (bool) $this->resetCooldownUntil()?->isFuture();
    }

    /** ¿Se puede resetear ahora? */
    public function canReset(): bool
    {
        return ! $this->isResetOnCooldown();
    }

    // ──────────────────────── STOCK SECUNDARIO (slots) ────────────────────────

    /** Capacidad de slots secundarios por plataforma. Fijo: 4 PS4 + 4 PS5. */
    public const SECONDARY_CAPACITY = [
        'PS4' => 4,
        'PS5' => 4,
    ];

    public function secondaryAssignments(): HasMany
    {
        return $this->hasMany(AccountSecondaryAssignment::class)->orderBy('slot_number');
    }

    /** Budget secundario de UNA plataforma (0 si la cuenta no la cubre o no aplica). */
    public function secondaryCapacityFor(string $platform): int
    {
        if (! in_array($platform, $this->coveredPlatforms(), true)) {
            return 0;
        }

        return self::SECONDARY_CAPACITY[$platform] ?? 0;
        // ← si en el futuro el reset penaliza el secundario, acá iría el match con reset_date
    }

    /** Cupos secundarios libres en UNA plataforma. */
    public function secondaryFreeSlotsFor(string $platform): int
    {
        $active = $this->relationLoaded('secondaryAssignments')
            ? $this->secondaryAssignments->where('status', 'active')
            : $this->secondaryAssignments()->where('status', 'active')->get();

        $used = $active->where('platform', $platform)->count();

        return max(0, $this->secondaryCapacityFor($platform) - $used);
    }

    /** Capacidad secundaria total (suma de plataformas cubiertas). */
    public function secondaryCapacity(): int
    {
        return collect($this->coveredPlatforms())
            ->sum(fn ($p) => $this->secondaryCapacityFor($p));
    }

    /** Cupos secundarios libres totales. */
    public function secondaryFreeSlots(): int
    {
        return collect($this->coveredPlatforms())
            ->sum(fn ($p) => $this->secondaryFreeSlotsFor($p));
    }

    /** Plataformas con stock secundario habilitado para esta cuenta. */
    public function secondaryPlatforms(): array
    {
        return collect($this->coveredPlatforms())
            ->filter(fn ($p) => $this->secondaryCapacityFor($p) > 0)
            ->values()
            ->all();
    }

    public const SECONDARY_ELIGIBLE_MONTHS = 2;

    /**
     * Fecha de referencia para el reloj de stock secundario, en cascada:
     *   1) última venta primaria activa CON fecha (assigned_at real)
     *   2) purchased_date (si los usos son placeholders sin fecha)
     *   3) created_at (último recurso: ni venta datada ni fecha de compra)
     */
    public function secondaryStockReference(): ?\Carbon\Carbon
    {
        if ($last = $this->lastAssignmentDate()) {
            return $last;
        }
        if ($this->purchased_date) {
            return \Carbon\Carbon::parse($this->purchased_date);
        }
        return $this->created_at;   // ya es Carbon (timestamp de Eloquent)
    }

    // public function isSecondaryStockEligible(): bool
    // {
    //     if ($this->freeSlots() > 0) {
    //         return false;                     // todavía hay cupos primarios
    //     }

    //     $ref = $this->secondaryStockReference();
    //     if ($ref === null) {
    //         return false;                     // no debería pasar (created_at siempre existe)
    //     }

    //     return $ref->copy()->addMonths(self::SECONDARY_ELIGIBLE_MONTHS)->isPast();
    // }

    public function secondaryEligibility(): array
    {
        $freePrimary = $this->freeSlots();
        $ref         = $this->secondaryStockReference();                 // cascada venta→compra→created_at
        $eligibleAt  = $ref?->copy()->addMonths(self::SECONDARY_ELIGIBLE_MONTHS);

        $eligible = $freePrimary === 0
            && $eligibleAt !== null
            && $eligibleAt->isPast();

        return [
            'eligible'     => $eligible,
            'free_primary' => $freePrimary,
            'reference'    => $ref,
            'source'       => $this->secondaryStockSource(),  // 'venta' | 'compra' | 'creación'
            'eligible_at'  => $eligibleAt,
        ];
    }


    /** De dónde salió la fecha de referencia del reloj secundario. */
    public function secondaryStockSource(): ?string
    {
        if ($this->lastAssignmentDate()) return 'venta';
        if ($this->purchased_date)       return 'compra';
        return 'creación';
    }
    public function isSecondaryStockEligible(): bool
    {
        return $this->canOfferSecondary();
    }

    
}
