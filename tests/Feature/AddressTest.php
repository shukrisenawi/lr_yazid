<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function data(string $line, bool $default = false): array
    {
        return ['full_name' => 'Customer', 'phone' => '012-3456789', 'line1' => $line, 'city' => 'Shah Alam', 'state' => 'Selangor', 'postcode' => '40100', 'is_default' => $default];
    }

    public function test_state_dropdown_uses_requested_names_and_order(): void
    {
        $this->actingAs(User::factory()->create())->get('/addresses')
            ->assertInertia(fn (Assert $page) => $page
                ->component('customer/Addresses')
                ->where('states', [
                    'Perlis', 'Kedah', 'Pulau Pinang', 'Perak', 'Kelantan', 'Terengganu',
                    'Pahang', 'Kuala Lumpur', 'Putrajaya', 'Selangor', 'Negeri Sembilan',
                    'Melaka', 'Johor', 'Labuan', 'Sabah', 'Sarawak',
                ]));
    }

    public function test_all_dropdown_states_can_be_saved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([
            'Perlis', 'Kedah', 'Pulau Pinang', 'Perak', 'Kelantan', 'Terengganu',
            'Pahang', 'Kuala Lumpur', 'Putrajaya', 'Selangor', 'Negeri Sembilan',
            'Melaka', 'Johor', 'Labuan', 'Sabah', 'Sarawak',
        ] as $state) {
            $this->post('/addresses', array_merge($this->data($state), [
                'state' => $state,
            ]))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'state' => $state]);
        }
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

    public function test_inertia_validation_returns_to_form_instead_of_previous_full_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertHeader('Referrer-Policy', 'same-origin');
        $this->get('/addresses', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ])->assertOk();

        $this->withHeaders([
            'X-Inertia' => 'true',
            'Referer' => url('/addresses'),
        ])->post('/addresses', array_merge($this->data('Test'), ['phone' => 'abc']))
            ->assertRedirect('/addresses')
            ->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('addresses', 0);
    }
}
