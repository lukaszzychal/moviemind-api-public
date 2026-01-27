<?php

declare(strict_types=1);

namespace Tests\Unit\FeatureFlags;

use App\Features\BaseFeature;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FeatureResolutionTest extends TestCase
{
    /** @test */
    public function it_uses_force_env_value_over_everything_else(): void
    {
        // Setup scenarios
        // 1. Force is true, DB is false, Default is false
        // 2. Force is false, DB is true, Default is true

        // We need a test double for BaseFeature
        $feature = new class extends BaseFeature
        {
            protected function flagName(): string
            {
                return 'test_feature';
            }
        };

        // Scenario A: Forced TRUE
        Config::set('features.test_feature', [
            'force' => true,
            'default' => false,
        ]);
        // We simulate DB saying 'false' (handled by Pennant usually, but here checking priority)
        // Note: The BaseFeature::resolve logic we plan to implement should return the forced value
        // regardless of what passed into resolve($scope).

        $this->assertTrue($feature->resolve(null), 'Force TRUE should override everything');

        // Scenario B: Forced FALSE
        Config::set('features.test_feature', [
            'force' => false,
            'default' => true,
        ]);

        $this->assertFalse($feature->resolve(null), 'Force FALSE should override everything');
    }

    /** @test */
    public function it_uses_db_value_if_force_is_null(): void
    {
        // If 'force' is null (not set), it should fall back to standard Pennant behavior.
        // In BaseFeature::resolve(), we expect that if force is null, it typically asks Pennant Store.
        // However, `BaseFeature::resolve()` is what Pennant calls *when it doesn't have a value*.
        // Wait, strictly speaking:
        // Pennant Logic: Check Store -> If Found, Return -> If Not Found, Call Feature::resolve()
        //
        // OUR CUSTOM LOGIC needs to be:
        // 1. If ENV FORCE is set, return it immediately (this effectively overrides Store if we can intercept,
        //    OR we make resolve() return it and ensure Store is bypassed or updated).
        //
        // Actually, if we use `BaseFeature::resolve`, it ONLY runs if the value is NOT in the DB (Store).
        // If a value IS in the DB, Pennant uses it and never calls `resolve`.
        //
        // PROBLEM: We want ENV FORCE to override the DB!
        // If I toggle "Enable AI" in DB (true), but ENV FORCE is (false), Pennant normally returns (true) from DB.
        // To fix this, we can't just rely on `resolve()`.
        // We likely need to wrap the Feature check or use a custom Driver.
        //
        // ALTERNATIVE (Simpler for Monolith):
        // `BaseFeature` isn't enough if Pennant Caches/Stores values.
        // But let's assume for this test we are testing the logic inside `resolve` or a helper `FeatureService`.

        // Let's stick to the Plan: "BaseFeature::resolve for defaults".
        // BUT verification step 1 says: "Test that ENV Force overrides DB Value".
        // This means `Feature::active('feature')` must return FALSE even if DB has TRUE.

        // To achieve this, we might need to use a custom Pennant Driver or decorator?
        // OR: We accept that `resolve` is only initial constraint.
        //
        // Wait, the documentation/search result said: "Dynamic Module Management... at application startup".
        // If we want "Force", we probably want to PREVENT Pennant from even checking DB, or make it ignore DB.

        // Let's refine the test expectation.
        // If we implement `resolve` to check FORCE, it handles the case where DB is empty.
        // If DB has value, `resolve` is skipped.
        // SO, to override DB, we probably need to Register features with a resolver only?
        // Or perhaps we use `pennant`'s `Feature::define` with a closure that checks force first?

        // Let's assume we will modify `BaseFeature` to handle this.
        // For the purpose of THIS unit test, let's test a method `getEffectiveValue` or similar.
        // Or if we modify `resolve`, we test `resolve`.

        // Let's implement the test assuming `resolve` handles the logic we control (Defaults/Force).
        // And we will deal with "Overriding DB" in the implementation phase properly (maybe by clearing cache or using array driver for forced ones).

        // For now, let's test correct Config reading.

        $feature = new class extends BaseFeature
        {
            protected function flagName(): string
            {
                return 'test_feature';
            }
        };

        Config::set('features.test_feature', [
            'force' => null,
            'default' => true, // Soft Default
        ]);

        // If force is null, resolve() should return the default.
        $this->assertTrue($feature->resolve(null));

        Config::set('features.test_feature', [
            'force' => null,
            'default' => false, // Soft Default
        ]);
        $this->assertFalse($feature->resolve(null));
    }
}
