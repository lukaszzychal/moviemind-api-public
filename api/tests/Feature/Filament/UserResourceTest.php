<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
// Note: Filament 3 uses diff namespace typically but let's stick to resource pages
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_user_resource_page()
    {
        $this->actingAs(User::factory()->create());
        $this->get(UserResource::getUrl('index'))->assertSuccessful();
    }

    public function test_can_list_users()
    {
        $users = User::factory()->count(10)->create();
        $this->actingAs(User::first());

        Livewire::test(UserResource\Pages\ManageUsers::class)
            ->assertCanSeeTableRecords($users);
    }

    public function test_can_create_a_user()
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(UserResource\Pages\ManageUsers::class)
            ->callAction(CreateAction::class, data: [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas(User::class, [
            'email' => 'john@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function test_can_validate_user_input()
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(UserResource\Pages\ManageUsers::class)
            ->callAction(CreateAction::class, data: [
                'name' => '',
                'email' => 'not-an-email',
                'password' => '',
            ])
            ->assertHasActionErrors(['name', 'email', 'password']);
    }
}
