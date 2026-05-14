<?php

namespace App\Services\Matchmaking;

/**
 * Resultado del matchmaking de un OrderItem.
 *
 * Si no hay candidatas viables, `emptyReason` explica POR QUÉ no las hay
 * (no es lo mismo "no tenemos cuentas de este juego" que "todas están sin cupos").
 */
readonly class MatchResult
{
    public function __construct(
        /** @var Candidate[] ordenadas: mejor primero */
        public array   $candidates,
        public ?string $emptyReason = null,
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->candidates);
    }

    public function best(): ?Candidate
    {
        return $this->candidates[0] ?? null;
    }

    /** Todas menos la mejor. Para el "ver otras opciones" del UI. */
    public function others(): array
    {
        return array_slice($this->candidates, 1);
    }

    public function count(): int
    {
        return count($this->candidates);
    }
}
