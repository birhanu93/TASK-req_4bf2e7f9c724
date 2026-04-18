<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;

/**
 * Moves old artifact files from hot tier directories to cold tier directories
 * once they exceed a configured age. Each named store (generic, certificates,
 * moderation uploads) keeps a resolution map so later reads can transparently
 * fetch either the hot or cold copy.
 */
final class StorageTieringService
{
    /**
     * @var array<string,array{hot:string,cold:string}>
     */
    private array $stores;

    public function __construct(
        string $hotDir,
        string $coldDir,
        private Clock $clock,
        private int $ageDaysThreshold = 180,
        array $additionalStores = [],
    ) {
        if ($ageDaysThreshold <= 0) {
            throw new ValidationException('ageDaysThreshold must be positive');
        }
        $this->stores = ['default' => ['hot' => $hotDir, 'cold' => $coldDir]];
        foreach ($additionalStores as $name => $pair) {
            if (!is_array($pair) || !isset($pair['hot'], $pair['cold'])) {
                throw new ValidationException("store '{$name}' must have hot+cold paths");
            }
            $this->stores[(string) $name] = ['hot' => (string) $pair['hot'], 'cold' => (string) $pair['cold']];
        }
        foreach ($this->stores as $store) {
            foreach ([$store['hot'], $store['cold']] as $d) {
                if (!is_dir($d) && !@mkdir($d, 0777, true) && !is_dir($d)) {
                    throw new \RuntimeException("failed to create tier directory {$d}");
                }
            }
        }
    }

    /**
     * Add or replace a named store. Used when wiring certificate / upload
     * directories after service construction so tests can pick custom roots.
     */
    public function registerStore(string $name, string $hotDir, string $coldDir): void
    {
        foreach ([$hotDir, $coldDir] as $d) {
            if (!is_dir($d) && !@mkdir($d, 0777, true) && !is_dir($d)) {
                throw new \RuntimeException("failed to create tier directory {$d}");
            }
        }
        $this->stores[$name] = ['hot' => $hotDir, 'cold' => $coldDir];
    }

    /**
     * @return array{moved:string[],kept:string[]}
     */
    public function tier(): array
    {
        $moved = [];
        $kept = [];
        $cutoff = $this->clock->now()->modify("-{$this->ageDaysThreshold} days")->getTimestamp();
        foreach ($this->stores as $store) {
            foreach (glob(rtrim($store['hot'], '/') . '/*') ?: [] as $path) {
                if (!is_file($path)) {
                    continue;
                }
                $mtime = filemtime($path);
                if ($mtime < $cutoff) {
                    $dest = rtrim($store['cold'], '/') . '/' . basename($path);
                    rename($path, $dest);
                    $moved[] = $dest;
                } else {
                    $kept[] = $path;
                }
            }
        }
        return ['moved' => $moved, 'kept' => $kept];
    }

    /**
     * Returns aggregate hot/cold counts plus a per-store breakdown. The flat
     * `hot` and `cold` keys keep the legacy single-store API working for
     * callers that only care about totals.
     *
     * @return array{hot:int,cold:int,stores:array<string,array{hot:int,cold:int}>}
     */
    public function snapshot(): array
    {
        $hot = 0;
        $cold = 0;
        $stores = [];
        foreach ($this->stores as $name => $store) {
            $h = count(glob(rtrim($store['hot'], '/') . '/*') ?: []);
            $c = count(glob(rtrim($store['cold'], '/') . '/*') ?: []);
            $stores[$name] = ['hot' => $h, 'cold' => $c];
            $hot += $h;
            $cold += $c;
        }
        return ['hot' => $hot, 'cold' => $cold, 'stores' => $stores];
    }

    /**
     * Resolve an artifact by its basename, returning the path in the hot tier
     * if still present or the cold tier otherwise. Returns null when the
     * artifact is not found in either tier.
     */
    public function resolve(string $storeName, string $basename): ?string
    {
        if (!isset($this->stores[$storeName])) {
            return null;
        }
        $basename = basename($basename);
        foreach (['hot', 'cold'] as $tier) {
            $p = rtrim($this->stores[$storeName][$tier], '/') . '/' . $basename;
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }
}
