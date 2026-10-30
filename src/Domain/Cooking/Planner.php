<?php

declare(strict_types=1);

namespace App\Domain\Cooking;

use App\Domain\Pantry\Ingredient;
use App\Domain\Pantry\Quantity;

final readonly class Planner
{
    /** @var list<Recipe> */
    private array $preps;

    /**
     * @param list<Recipe> $recipes
     */
    public function __construct(array $recipes)
    {
        $this->preps = array_values(array_filter($recipes, static fn (Recipe $recipe): bool => $recipe->isPrep()));
    }

    public function plan(Recipe $recipe): Plan
    {
        $stock = [];
        $preps = [];
        $uses = [];
        $waiting = [];
        $missing = [];
        $visiting = [(string) $recipe->id() => true];

        foreach ($recipe->lines() as $line) {
            $id = (string) $line->ingredient()->id();
            $made = \count($preps);
            $used = $this->take($line->ingredient(), $line->amount(), $stock, $preps, $visiting);
            if (null === $used) {
                $missing[] = $line;
                continue;
            }
            $uses[$id] = $used;
            if (\count($preps) > $made) {
                $waiting[$id] = true;
            }
        }

        $distinct = [];
        foreach ($preps as $prep) {
            $distinct[(string) $prep->id()] ??= $prep;
        }

        return new Plan($uses, $waiting, $missing, array_values($distinct));
    }

    /**
     * @param array<string, int>  $stock
     * @param list<Recipe>        $preps
     * @param array<string, true> $visiting
     */
    private function take(Ingredient $wanted, ?Quantity $amount, array &$stock, array &$preps, array $visiting): ?Ingredient
    {
        $family = $wanted->family();

        foreach ($family as $candidate) {
            if ($this->covers($wanted, $candidate, $amount, $stock)) {
                $this->consume($wanted, $candidate, $amount, $stock);

                return $candidate;
            }
        }

        foreach ($family as $candidate) {
            foreach ($this->preps as $prep) {
                if ($prep->output() !== $candidate || isset($visiting[(string) $prep->id()])) {
                    continue;
                }
                $trialStock = $stock;
                $trialPreps = $preps;
                while (!$this->covers($wanted, $candidate, $amount, $trialStock)) {
                    $before = $this->stockOf($candidate, $trialStock);
                    if (!$this->make($prep, $trialStock, $trialPreps, $visiting) || $this->stockOf($candidate, $trialStock) <= $before) {
                        continue 2;
                    }
                }
                $stock = $trialStock;
                $preps = $trialPreps;
                $this->consume($wanted, $candidate, $amount, $stock);

                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string, int>  $stock
     * @param list<Recipe>        $preps
     * @param array<string, true> $visiting
     */
    private function make(Recipe $prep, array &$stock, array &$preps, array $visiting): bool
    {
        $visiting[(string) $prep->id()] = true;
        foreach ($prep->lines() as $line) {
            if (null === $this->take($line->ingredient(), $line->amount(), $stock, $preps, $visiting)) {
                return false;
            }
        }

        $output = $prep->output();
        $yield = $prep->yieldAmount();
        if (null === $output || null === $yield) {
            return false;
        }
        $stock[(string) $output->id()] = $this->stockOf($output, $stock) + $yield->twelfths();
        $preps[] = $prep;

        return true;
    }

    /**
     * @param array<string, int> $stock
     */
    private function covers(Ingredient $wanted, Ingredient $candidate, ?Quantity $amount, array $stock): bool
    {
        $have = $this->stockOf($candidate, $stock);

        return null !== $amount && self::deducts($wanted, $candidate) ? $have >= $amount->twelfths() : $have > 0;
    }

    /**
     * @param array<string, int> $stock
     */
    private function consume(Ingredient $wanted, Ingredient $candidate, ?Quantity $amount, array &$stock): void
    {
        if (null !== $amount && self::deducts($wanted, $candidate)) {
            $stock[(string) $candidate->id()] = $this->stockOf($candidate, $stock) - $amount->twelfths();
        }
    }

    /**
     * @param array<string, int> $stock
     */
    private function stockOf(Ingredient $ingredient, array $stock): int
    {
        return $stock[(string) $ingredient->id()] ?? $ingredient->stock()->twelfths();
    }

    private static function deducts(Ingredient $wanted, Ingredient $candidate): bool
    {
        return !$candidate->isManual() && $candidate->unit() === $wanted->unit();
    }
}
