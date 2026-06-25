<?php

declare(strict_types=1);

namespace App\Services\Affiliate;

interface AffiliateServiceInterface
{
    public function name(): string;
    /** @return array<int,array<string,mixed>> */
    public function search(string $keyword, array $credentials): array;
}
