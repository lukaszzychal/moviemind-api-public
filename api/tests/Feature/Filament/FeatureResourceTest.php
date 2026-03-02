<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Use string reference if class doesn't exist yet, or accept failure
// use App\Filament\Resources\FeatureResource;

class FeatureResourceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_access_feature_resource_page()
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        // We guess the URL structure for verification until Resource is created
        $response = $this->actingAs($user)
            ->get('/admin/features');

        // Assert
        $response->assertSuccessful();
    }
}
