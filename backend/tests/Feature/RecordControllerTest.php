<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\Record;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that records can be listed.
     */
    public function test_can_list_records(): void
    {
        // Create test data
        Record::factory()->count(3)->create();

        // Make request
        $response = $this->getJson('/api/records');

        // Assert response
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => [
                             'id',
                             'title',
                             'genre',
                             'year',
                         ]
                     ],
                     'links',
                     'meta',
                 ]);
    }

    /**
     * Test that records are paginated.
     */
    public function test_records_are_paginated(): void
    {
        Record::factory()->count(25)->create();

        $response = $this->getJson('/api/records');

        $response->assertStatus(200)
                 ->assertJsonPath('meta.per_page', 20)
                 ->assertJsonPath('meta.total', 25);
    }

    /**
     * Test that a single record can be viewed.
     */
    public function test_can_show_single_record(): void
    {
        $record = Record::factory()->create([
            'title' => 'Test Album',
            'year' => 1985,
        ]);

        $response = $this->getJson("/api/records/{$record->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $record->id)
                 ->assertJsonPath('data.title', 'Test Album')
                 ->assertJsonPath('data.year', 1985);
    }

    /**
     * Test that record includes artist when loaded.
     */
    public function test_record_includes_artist(): void
    {
        $artist = Artist::factory()->create([
            'display_name' => 'The Beatles',
        ]);

        $record = Record::factory()->create([
            'artist_id' => $artist->id,
        ]);

        $response = $this->getJson("/api/records/{$record->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.artist.id', $artist->id)
                 ->assertJsonPath('data.artist.display_name', 'The Beatles');
    }

    /**
     * Test that a record can be created with valid data.
     */
    public function test_can_create_record(): void
    {
        $artist = Artist::factory()->create();

        $data = [
            'title' => 'New Album',
            'artist_id' => $artist->id,
            'year' => 2024,
            'genre' => 'Rock',
            'description' => 'A great album',
        ];

        $response = $this->postJson('/api/records', $data);

        $response->assertStatus(201)
                 ->assertJsonPath('data.title', 'New Album')
                 ->assertJsonPath('data.year', 2024)
                 ->assertJsonPath('data.genre', 'Rock');

        $this->assertDatabaseHas('records', [
            'title' => 'New Album',
            'artist_id' => $artist->id,
            'year' => 2024,
        ]);
    }

    /**
     * Test that validation fails without required fields.
     */
    public function test_cannot_create_record_without_required_fields(): void
    {
        $response = $this->postJson('/api/records', [
            'title' => 'Test Album',
            // Missing artist_id and year
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['artist_id', 'year']);
    }

    /**
     * Test that validation fails with invalid year.
     */
    public function test_cannot_create_record_with_invalid_year(): void
    {
        $artist = Artist::factory()->create();

        $response = $this->postJson('/api/records', [
            'title' => 'Test Album',
            'artist_id' => $artist->id,
            'year' => 1800, // Too old
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['year']);
    }

    /**
     * Test that validation fails with non-existent artist.
     */
    public function test_cannot_create_record_with_nonexistent_artist(): void
    {
        $response = $this->postJson('/api/records', [
            'title' => 'Test Album',
            'artist_id' => 99999, // Non-existent
            'year' => 2024,
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['artist_id']);
    }

    /**
     * Test that a record can be updated.
     */
    public function test_can_update_record(): void
    {
        $record = Record::factory()->create([
            'title' => 'Original Title',
        ]);

        $response = $this->putJson("/api/records/{$record->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('records', [
            'id' => $record->id,
            'title' => 'Updated Title',
        ]);
    }

    /**
     * Test that a record can be deleted.
     */
    public function test_can_delete_record(): void
    {
        $record = Record::factory()->create();

        $response = $this->deleteJson("/api/records/{$record->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Record deleted successfully');

        $this->assertDatabaseMissing('records', [
            'id' => $record->id,
        ]);
    }

    /**
     * Test that 404 is returned for non-existent record.
     */
    public function test_returns_404_for_nonexistent_record(): void
    {
        $response = $this->getJson('/api/records/99999');

        $response->assertStatus(404);
    }

    /**
     * Test that record includes variants in stock.
     */
    public function test_record_includes_variants_in_stock(): void
    {
        $record = Record::factory()->create();

        // Create variants: 2 in stock, 1 out of stock
        Variant::factory()->count(2)->create([
            'record_id' => $record->id,
            'stock' => 5,
        ]);

        Variant::factory()->create([
            'record_id' => $record->id,
            'stock' => 0, // Out of stock
        ]);

        $response = $this->getJson("/api/records/{$record->id}");

        $response->assertStatus(200);

        // Should only show variants in stock (2)
        $data = $response->json('data');
        $this->assertCount(2, $data['variants']);
    }

    /**
     * Test in_stock flag when record has variants in stock.
     */
    public function test_in_stock_is_true_when_variants_available(): void
    {
        $record = Record::factory()->create();

        Variant::factory()->create([
            'record_id' => $record->id,
            'stock' => 10,
        ]);

        $response = $this->getJson("/api/records/{$record->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.in_stock', true);
    }

    /**
     * Test in_stock flag when no variants in stock.
     */
    public function test_in_stock_is_false_when_no_variants_available(): void
    {
        $record = Record::factory()->create();

        // Create variant with 0 stock
        Variant::factory()->create([
            'record_id' => $record->id,
            'stock' => 0,
        ]);

        $response = $this->getJson("/api/records/{$record->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.in_stock', false);
    }
}
