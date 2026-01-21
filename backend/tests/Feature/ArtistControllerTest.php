<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtistControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that artists can be listed.
     */
    public function test_can_list_artists(): void
    {
        Artist::factory()->count(3)->create();

        $response = $this->getJson('/api/artists');

        $response->assertStatus(200)
                 ->assertJsonCount(3, 'data');
    }

    /**
     * Test that a single artist can be viewed.
     */
    public function test_can_show_single_artist(): void
    {
        $artist = Artist::factory()->create([
            'display_name' => 'Test Artist',
            'bio' => 'A great musician',
        ]);

        $response = $this->getJson("/api/artists/{$artist->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('data.id', $artist->id)
                 ->assertJsonPath('data.display_name', 'Test Artist')
                 ->assertJsonPath('data.bio', 'A great musician');
    }

    /**
     * Test that 404 is returned for non-existent artist.
     */
    public function test_returns_404_for_nonexistent_artist(): void
    {
        $response = $this->getJson('/api/artists/99999');

        $response->assertStatus(404);
    }
}
