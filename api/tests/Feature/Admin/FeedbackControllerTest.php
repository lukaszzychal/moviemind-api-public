<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ApplicationFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-admin-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Config::set('admin.api_token', $this->token);

        // Bypass Admin API auth for tests (so requests without token also work in CI)
        config(['app.env' => 'testing']);
        config(['admin.auth.bypass_environments' => ['local', 'staging', 'testing']]);
    }

    public function test_admin_can_list_feedback(): void
    {
        ApplicationFeedback::create([
            'message' => 'First feedback',
            'status' => ApplicationFeedback::STATUS_PENDING,
        ]);

        $res = $this->withHeader('X-Admin-Token', $this->token)
            ->getJson('/api/v1/admin/feedback');

        $res->assertOk()->assertJsonStructure(['data', 'current_page']);
        $this->assertGreaterThanOrEqual(1, count($res->json('data')));
    }

    public function test_admin_can_show_feedback(): void
    {
        $feedback = ApplicationFeedback::create([
            'message' => 'Detail feedback',
            'category' => 'bug',
            'status' => ApplicationFeedback::STATUS_PENDING,
        ]);

        $res = $this->withHeader('X-Admin-Token', $this->token)
            ->getJson('/api/v1/admin/feedback/'.$feedback->id);

        $res->assertOk()
            ->assertJson([
                'message' => 'Detail feedback',
                'category' => 'bug',
                'status' => 'pending',
            ]);
    }

    public function test_admin_show_returns_404_for_missing(): void
    {
        $res = $this->withHeader('X-Admin-Token', $this->token)
            ->getJson('/api/v1/admin/feedback/00000000-0000-0000-0000-000000000000');

        $res->assertStatus(404);
    }

    public function test_admin_can_update_status(): void
    {
        $feedback = ApplicationFeedback::create([
            'message' => 'To be read',
            'status' => ApplicationFeedback::STATUS_PENDING,
        ]);

        $res = $this->withHeader('X-Admin-Token', $this->token)
            ->patchJson('/api/v1/admin/feedback/'.$feedback->id, [
                'status' => ApplicationFeedback::STATUS_READ,
            ]);

        $res->assertOk()->assertJson(['status' => 'read']);
        $feedback->refresh();
        $this->assertSame(ApplicationFeedback::STATUS_READ, $feedback->status);
    }

    public function test_admin_can_delete_feedback(): void
    {
        $feedback = ApplicationFeedback::create([
            'message' => 'To delete',
            'status' => ApplicationFeedback::STATUS_PENDING,
        ]);

        $res = $this->withHeader('X-Admin-Token', $this->token)
            ->deleteJson('/api/v1/admin/feedback/'.$feedback->id);

        $res->assertStatus(204);
        $this->assertDatabaseMissing('application_feedback', ['id' => $feedback->id]);
    }

    public function test_admin_delete_returns_404_for_missing(): void
    {
        $res = $this->withHeader('X-Admin-Token', $this->token)
            ->deleteJson('/api/v1/admin/feedback/00000000-0000-0000-0000-000000000000');

        $res->assertStatus(404);
    }
}
