<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AuditLogger: jalur kanonis penulisan audit_logs; nilai rahasia disamarkan.
 */
class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_writes_row_with_actor_and_values(): void
    {
        $actor = User::factory()->create();

        app(AuditLogger::class)->log(
            action: 'things.happened',
            subjectType: 'thing',
            subjectId: 42,
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'done'],
            branchId: null,
            actor: $actor,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'things.happened',
            'subject_type' => 'thing',
            'subject_id' => 42,
            'user_id' => $actor->id,
        ]);

        $log = AuditLog::first();
        $this->assertSame(['status' => 'draft'], $log->old_values);
        $this->assertSame(['status' => 'done'], $log->new_values);
    }

    public function test_sensitive_keys_are_masked_recursively(): void
    {
        app(AuditLogger::class)->log(
            action: 'users.update',
            subjectType: 'user',
            subjectId: 1,
            newValues: ['password' => 'rahasia', 'nested' => ['api_token' => 'abc123', 'name' => 'Aman']],
        );

        $log = AuditLog::first();
        $this->assertSame('***', $log->new_values['password']);
        $this->assertSame('***', $log->new_values['nested']['api_token']);
        $this->assertSame('Aman', $log->new_values['nested']['name']);
    }

    public function test_actor_defaults_to_authenticated_user(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        app(AuditLogger::class)->log(action: 'x.y', subjectType: 'thing');

        $this->assertDatabaseHas('audit_logs', ['action' => 'x.y', 'user_id' => $actor->id]);
    }
}
