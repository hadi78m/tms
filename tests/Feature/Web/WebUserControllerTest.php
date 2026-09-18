<?php

namespace Tests\Feature\Web;

use App\Models\SyncedContractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebUserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'contractor', 'guard_name' => 'web']);
    }

    public function test_admin_can_access_users_index_and_create_pages(): void
    {
        $admin = User::factory()->create(['contractor_id' => null]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/users');
        $response->assertStatus(200);
        $response->assertSee('فهرست کاربران سامانه');

        $createResponse = $this->actingAs($admin)->get('/users/create');
        $createResponse->assertStatus(200);
        $createResponse->assertSee('مشخصات کاربر جدید');
    }

    public function test_supervisor_and_contractor_cannot_access_user_management(): void
    {
        $supervisor = User::factory()->create(['contractor_id' => null]);
        $supervisor->assignRole('supervisor');

        $contractorUser = User::factory()->create();
        $contractorUser->assignRole('contractor');

        $responseSupervisor = $this->actingAs($supervisor)->get('/users');
        $responseSupervisor->assertStatus(403);

        $responseContractor = $this->actingAs($contractorUser)->get('/users');
        $responseContractor->assertStatus(403);
    }

    public function test_admin_can_store_new_user_with_roles_and_hashed_password(): void
    {
        $admin = User::factory()->create(['contractor_id' => null]);
        $admin->assignRole('admin');

        $contractor = SyncedContractor::factory()->create();

        $payload = [
            'name' => 'Reza Ahmadi',
            'national_code' => '9876543210',
            'mobile' => '09351234567',
            'email' => 'reza@example.com',
            'password' => 'secret1234',
            'contractor_id' => $contractor->id,
            'roles' => ['contractor'],
            'is_active' => '1',
        ];

        $response = $this->actingAs($admin)->post('/users', $payload);

        $response->assertRedirect('/users');
        $response->assertSessionHas('status');

        $newUser = User::where('national_code', '9876543210')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals('9876543210', $newUser->username);
        $this->assertEquals('Reza Ahmadi', $newUser->name);
        $this->assertEquals($contractor->id, $newUser->contractor_id);
        $this->assertTrue(Hash::check('secret1234', $newUser->password));
        $this->assertTrue($newUser->hasRole('contractor'));
    }

    public function test_admin_can_update_user_and_change_roles(): void
    {
        $admin = User::factory()->create(['contractor_id' => null]);
        $admin->assignRole('admin');

        $user = User::factory()->create([
            'name' => 'Old Name',
            'national_code' => '5555555555',
            'mobile' => '09120000000',
        ]);
        $user->assignRole('supervisor');

        $updatePayload = [
            'name' => 'Updated Name',
            'national_code' => '5555555555',
            'mobile' => '09129999999',
            'email' => 'updated@example.com',
            'roles' => ['admin'],
            'is_active' => '1',
        ];

        $response = $this->actingAs($admin)->put("/users/{$user->id}", $updatePayload);

        $response->assertRedirect('/users');

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('09129999999', $user->mobile);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('supervisor'));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->create(['contractor_id' => null]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->delete("/users/{$admin->id}");

        $response->assertRedirect('/users');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
    }
}
