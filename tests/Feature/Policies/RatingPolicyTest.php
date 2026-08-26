<?php

namespace Tests\Feature\Policies;

use App\Models\AccountStatus;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\DeliveryProvider;
use App\Models\DeliveryService;
use App\Models\Rating;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RatingPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_supported_roles_can_access_the_rating_list(): void
    {
        $customer = Customer::factory()->create()->user;
        $provider = DeliveryProvider::factory()->create()->user;
        $supportAgent = $this->userWithRole('SUPPORT_AGENT');
        $administrator = $this->userWithRole('ADMINISTRATOR');
        $courier = Courier::factory()->create()->user;

        foreach ([
            $customer,
            $provider,
            $supportAgent,
            $administrator,
        ] as $user) {
            $this->assertTrue(
                $this->allows(
                    $user,
                    'viewAny',
                    Rating::class
                )
            );
        }

        $this->assertFalse(
            $this->allows(
                $courier,
                'viewAny',
                Rating::class
            )
        );
    }

    public function test_an_inactive_user_cannot_access_ratings(): void
    {
        $customer = Customer::factory()->create();
        $rating = $this->ratingFor($customer);

        $suspendedStatus = AccountStatus::query()
            ->where('status_name', 'SUSPENDED')
            ->firstOrFail();

        $customer->user->update([
            'account_status_id' => $suspendedStatus->id,
        ]);

        $user = $customer->user->fresh();

        $this->assertFalse(
            $this->allows($user, 'view', $rating)
        );

        $this->assertFalse(
            $this->allows(
                $user,
                'create',
                Rating::class
            )
        );
    }

    public function test_a_customer_with_a_profile_can_create_ratings(): void
    {
        $customer = Customer::factory()->create();
        $userWithoutProfile = User::factory()->customer()->create();

        $this->assertTrue(
            $this->allows(
                $customer->user,
                'create',
                Rating::class
            )
        );

        $this->assertFalse(
            $this->allows(
                $userWithoutProfile,
                'create',
                Rating::class
            )
        );
    }

    public function test_a_customer_can_view_their_own_rating(): void
    {
        $customer = Customer::factory()->create();
        $rating = $this->ratingFor($customer);

        $this->assertTrue(
            $this->allows(
                $customer->user,
                'view',
                $rating
            )
        );
    }

    public function test_a_customer_cannot_view_another_customers_rating(): void
    {
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();

        $rating = $this->ratingFor($otherCustomer);

        $this->assertFalse(
            $this->allows(
                $customer->user,
                'view',
                $rating
            )
        );
    }

    public function test_only_the_linked_provider_can_view_the_rating(): void
    {
        $customer = Customer::factory()->create();
        $linkedProvider = DeliveryProvider::factory()->create();
        $unrelatedProvider = DeliveryProvider::factory()->create();

        $rating = $this->ratingFor(
            $customer,
            $linkedProvider
        );

        $this->assertTrue(
            $this->allows(
                $linkedProvider->user,
                'view',
                $rating
            )
        );

        $this->assertFalse(
            $this->allows(
                $unrelatedProvider->user,
                'view',
                $rating
            )
        );
    }

    public function test_support_and_administration_can_view_ratings(): void
    {
        $rating = $this->ratingFor(
            Customer::factory()->create()
        );

        $supportAgent = $this->userWithRole('SUPPORT_AGENT');
        $administrator = $this->userWithRole('ADMINISTRATOR');

        $this->assertTrue(
            $this->allows(
                $supportAgent,
                'view',
                $rating
            )
        );

        $this->assertTrue(
            $this->allows(
                $administrator,
                'view',
                $rating
            )
        );
    }

    public function test_ratings_cannot_be_modified_or_deleted(): void
    {
        $customer = Customer::factory()->create();
        $rating = $this->ratingFor($customer);
        $user = $customer->user;

        foreach ([
            'update',
            'delete',
            'restore',
            'forceDelete',
        ] as $ability) {
            $this->assertFalse(
                $this->allows($user, $ability, $rating)
            );
        }
    }

    private function ratingFor(
        Customer $customer,
        ?DeliveryProvider $provider = null
    ): Rating {
        $shipment = Shipment::factory()->create([
            'customer_id' => $customer->id,
        ]);

        $serviceAttributes = [
            'shipment_id' => $shipment->id,
            'status' => 'COMPLETED',
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'delivery_fee' => 100,
        ];

        if ($provider !== null) {
            $trip = Trip::factory()->create([
                'delivery_provider_id' => $provider->id,
                'status' => 'USED',
                'used_at' => now()->subHour(),
            ]);

            $serviceAttributes = array_merge(
                $serviceAttributes,
                [
                    'trip_id' => $trip->id,
                    'trip_type_id' => $trip->trip_type_id,
                    'accepted_at' => now()->subHours(2),
                ]
            );
        }

        $service = DeliveryService::factory()->create(
            $serviceAttributes
        );

        return Rating::query()->create([
            'delivery_service_id' => $service->id,
            'customer_id' => $customer->id,
            'punctuality_score' => 5,
            'customer_service_score' => 4,
            'package_condition_score' => 5,
            'overall_score' => 4.67,
            'comment' => 'Excellent delivery service.',
            'rated_at' => now(),
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::query()
            ->where('role_name', $roleName)
            ->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }

    private function allows(
        User $user,
        string $ability,
        mixed $arguments
    ): bool {
        return Gate::forUser($user)->allows(
            $ability,
            $arguments
        );
    }
}
