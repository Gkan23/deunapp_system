<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\AccountStatus;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentStatus;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentIndexPageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_a_guest_cannot_view_the_shipment_page(): void
    {
        $this->get(
            route('portal.shipments.index')
        )->assertRedirect(
            route('login.page')
        );
    }

    public function test_an_unverified_user_is_redirected_to_verification(): void
    {
        $customer = Customer::factory()->create();

        $user = $customer->user;

        /*
         * email_verified_at no pertenece a $fillable,
         * por eso la prueba utiliza forceFill().
         */
        $user->forceFill([
            'email_verified_at' => null,
        ])->save();

        $user = $user->fresh();

        $this->assertFalse(
            $user->hasVerifiedEmail()
        );

        $this->actingAs($user)
            ->get(
                route('portal.shipments.index')
            )
            ->assertRedirect(
                route('verification.notice')
            );
    }

    public function test_a_customer_only_sees_their_shipments(): void
    {
        $customer = Customer::factory()->create();

        $otherCustomer =
            Customer::factory()->create();

        $ownShipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
            'tracking_code' => 'DUNA-OWN-SHIPMENT',
        ]);

        $otherShipment =
            Shipment::factory()->create([
                'customer_id' =>
                    $otherCustomer->id,
                'tracking_code' =>
                    'DUNA-OTHER-SHIPMENT',
            ]);

        $this->actingAs($customer->user)
            ->get(
                route('portal.shipments.index')
            )
            ->assertOk()
            ->assertSee(
                $ownShipment->tracking_code
            )
            ->assertDontSee(
                $otherShipment->tracking_code
            );
    }

    public function test_shipments_can_be_filtered_by_tracking_and_status(): void
    {
        $customer = Customer::factory()->create();

        $deliveredStatus =
            ShipmentStatus::query()
                ->where(
                    'status_name',
                    'DELIVERED'
                )
                ->firstOrFail();

        $matchingShipment =
            Shipment::factory()->create([
                'customer_id' => $customer->id,
                'tracking_code' =>
                    'DUNA-FILTER-001',
                'shipment_status_id' =>
                    $deliveredStatus->id,
            ]);

        $otherShipment =
            Shipment::factory()->create([
                'customer_id' => $customer->id,
                'tracking_code' =>
                    'DUNA-OTHER-001',
            ]);

        $this->actingAs($customer->user)
            ->get(
                route(
                    'portal.shipments.index',
                    [
                        'search' => 'FILTER',
                        'status' => 'DELIVERED',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $matchingShipment->tracking_code
            )
            ->assertDontSee(
                $otherShipment->tracking_code
            )
            ->assertSee(
                'value="FILTER"',
                escape: false
            )
            ->assertSee(
                'value="DELIVERED"',
                escape: false
            );
    }

    public function test_the_page_displays_an_empty_state(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer->user)
            ->get(
                route('portal.shipments.index')
            )
            ->assertOk()
            ->assertSee(
                'No se encontraron envíos'
            )
            ->assertSee(
                '0'
            );
    }

    public function test_an_inactive_account_cannot_view_the_page(): void
    {
        $customer = Customer::factory()->create();

        $inactiveStatus = AccountStatus::query()
            ->where(
                'status_name',
                '!=',
                'ACTIVE'
            )
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' =>
                $inactiveStatus->id,
        ]);

        $this->actingAs(
            $customer->user->fresh()
        )
            ->get(
                route('portal.shipments.index')
            )
            ->assertForbidden();
    }
}