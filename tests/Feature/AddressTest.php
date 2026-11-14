<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function data(string $line, bool $default = false): array
    {
        return ['full_name' => 'Customer', 'phone' => '012-3456789', 'line1' => $line, 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100', 'is_default' => $default];
    }

    public function test_addresses_normalize_phone_and_keep_exactly_one_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/addresses', $this->data('First'))->assertSessionHasNoErrors();
        $this->post('/addresses', $this->data('Second', true))->assertSessionHasNoErrors();
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->assertDatabaseHas('addresses', ['line1' => 'Second', 'phone' => '+60123456789', 'is_default' => true]);
        $default = $user->addresses()->where('is_default', true)->firstOrFail();
        $this->delete("/addresses/$default->id")->assertRedirect();
        $this->assertDatabaseHas('addresses', ['line1' => 'First', 'is_default' => true]);
    }

    public function test_another_users_address_cannot_be_edited_or_deleted(): void
    {
        $this->actingAs(User::factory()->create())->post('/addresses', $this->data('Private'));
        $address = Address::firstOrFail();
        $this->actingAs(User::factory()->create())->put("/addresses/$address->id", $this->data('Hacked'))->assertForbidden();
        $this->delete("/addresses/$address->id")->assertForbidden();
    }

    public function test_invalid_state_postcode_and_phone_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post('/addresses', array_merge($this->data('Test'), [
            'state' => 'Invalid', 'postcode' => '123', 'phone' => '+441234567890',
        ]))->assertSessionHasErrors(['state', 'postcode', 'phone']);
    }
}
