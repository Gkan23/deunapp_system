<?php

namespace Tests\Feature\Services\Delivery;

use App\Models\Address;
use App\Models\Municipality;
use App\Services\Delivery\ResolveTripTypeService;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTripTypeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    public function test_it_resolves_local_trip_for_the_same_municipality(): void
    {
        $municipality = Municipality::query()
            ->where('municipality_name', 'Estelí')
            ->firstOrFail();

        $originAddress = Address::factory()->create([
            'municipality_id' => $municipality->id,
        ]);

        $destinationAddress = Address::factory()->create([
            'municipality_id' => $municipality->id,
        ]);

        $tripType = app(ResolveTripTypeService::class)->handle(
            $originAddress,
            $destinationAddress
        );

        $this->assertSame('LOCAL', $tripType->type_name);
    }

    public function test_it_resolves_intermunicipal_trip_for_different_municipalities(): void
    {
        $esteli = Municipality::query()
            ->where('municipality_name', 'Estelí')
            ->firstOrFail();

        $condega = Municipality::query()
            ->where('municipality_name', 'Condega')
            ->firstOrFail();

        $originAddress = Address::factory()->create([
            'municipality_id' => $esteli->id,
        ]);

        $destinationAddress = Address::factory()->create([
            'municipality_id' => $condega->id,
        ]);

        $tripType = app(ResolveTripTypeService::class)->handle(
            $originAddress,
            $destinationAddress
        );

        $this->assertSame(
            'INTERMUNICIPAL',
            $tripType->type_name
        );
    }
}
