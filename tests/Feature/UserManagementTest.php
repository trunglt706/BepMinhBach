<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /** T05 */
    public function test_admin_can_create_user_with_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Tình nguyện viên mới',
            'email' => 'TNV.Moi@Example.com',
            'role' => 'volunteer',
            'password' => 'mat-khau-123',
            'password_confirmation' => 'mat-khau-123',
        ])->assertRedirect('/users');

        $user = User::where('email', 'tnv.moi@example.com')->firstOrFail();
        $this->assertSame(Role::Volunteer, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('mat-khau-123', $user->password);

        $log = ActivityLog::where('action', 'user.created')->firstOrFail();
        $this->assertStringNotContainsString('mat-khau-123', $log->summary);
    }

    public function test_admin_can_change_role_and_reset_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->volunteer()->create(['password' => 'mat-khau-cu']);

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'recorder',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/users');

        $user->refresh();
        $this->assertSame(Role::Recorder, $user->role);
        $this->assertTrue(password_verify('mat-khau-cu', $user->password), 'Mật khẩu để trống thì không đổi');

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'name' => $user->name, 'email' => $user->email, 'role' => 'recorder',
            'password' => 'mat-khau-moi-1', 'password_confirmation' => 'mat-khau-moi-1',
        ]);
        $this->assertTrue(password_verify('mat-khau-moi-1', $user->fresh()->password));
    }

    public function test_admin_can_lock_and_unlock_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->post("/users/{$user->id}/toggle-active")->assertRedirect('/users');
        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($admin)->post("/users/{$user->id}/toggle-active");
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_admin_cannot_lock_or_demote_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post("/users/{$admin->id}/toggle-active")->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'role' => 'volunteer',
        ])->assertSessionHasErrors('role');
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_duplicate_email_and_invalid_role_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['email' => 'trung@example.com']);

        $this->actingAs($admin)->post('/users', [
            'name' => 'A', 'email' => 'trung@example.com', 'role' => 'superuser',
            'password' => 'mat-khau-123', 'password_confirmation' => 'mat-khau-123',
        ])->assertSessionHasErrors(['email', 'role']);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $other = User::factory()->create();

        $this->actingAs($coordinator)->get('/users')->assertForbidden();
        $this->actingAs($coordinator)->get('/users/create')->assertForbidden();
        $this->actingAs($coordinator)->post('/users', [
            'name' => 'X', 'email' => 'x@example.com', 'role' => 'admin',
            'password' => 'mat-khau-123', 'password_confirmation' => 'mat-khau-123',
        ])->assertForbidden();
        $this->actingAs($coordinator)->put("/users/{$coordinator->id}", [
            'name' => 'X', 'email' => $coordinator->email, 'role' => 'admin',
        ])->assertForbidden();
        $this->actingAs($coordinator)->post("/users/{$other->id}/toggle-active")->assertForbidden();

        $this->assertSame(Role::Coordinator, $coordinator->fresh()->role);
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_users_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->delete("/users/{$user->id}")->assertStatus(405);
        $this->assertModelExists($user);
    }

    public function test_user_list_does_not_expose_passwords(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->get('/users')->assertOk()
            ->assertSee($user->email)
            ->assertDontSee($user->password);
    }
}
