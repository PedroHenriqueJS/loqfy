<?php

namespace App\Services;

use App\Models\Equipment;
use App\Support\Text;
use Illuminate\Support\Collection;

class CatalogSearch
{
    private const STOPWORDS = ['de', 'da', 'do', 'para', 'pra', 'com', 'um', 'uma', 'e', 'o', 'a', 'tipo', 'x', 'l', 'litro', 'litros', 'kg', 'quilos', 'kva', 'm', 'metros', 't', 'pes', 'polegadas'];

    public function search(string $term, int $limit = 5): Collection
    {
        $wanted = $this->words($term);

        if ($wanted === []) {
            return collect();
        }

        $scored = Equipment::all()
            ->map(fn (Equipment $equipment) => ['equipment' => $equipment, 'score' => $this->score($wanted, $equipment)])
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score');

        $best = $scored->max('score') ?? 0;

        return $scored
            ->filter(fn (array $row) => $row['score'] >= 0.8 * $best)
            ->take($limit)
            ->pluck('equipment')
            ->values();
    }

    private function score(array $wanted, Equipment $equipment): float
    {
        $best = 0.0;

        foreach ([$equipment->name, ...$equipment->aliases, $equipment->category] as $candidate) {
            $have = $this->words($candidate);
            $hits = 0.0;

            foreach ($wanted as $token) {
                $hits += $this->tokenMatch($token, $have);
            }

            $coverage = $hits / count($wanted);
            $tightness = $hits / max(count($have), 1);
            $best = max($best, $coverage + 0.25 * $tightness);
        }

        return $best >= 0.5 ? $best : 0.0;
    }

    private function words(string $text): array
    {
        return array_values(array_diff(Text::tokens($text), self::STOPWORDS));
    }

    private function tokenMatch(string $token, array $have): float
    {
        foreach ($have as $candidate) {
            if ($candidate === $token) {
                return 1.0;
            }
        }

        foreach ($have as $candidate) {
            if (min(strlen($token), strlen($candidate)) >= 4 && (str_starts_with($candidate, $token) || str_starts_with($token, $candidate))) {
                return 0.8;
            }

            if (min(strlen($token), strlen($candidate)) >= 5 && levenshtein($token, $candidate) <= 2) {
                return 0.7;
            }
        }

        return 0.0;
    }
}
