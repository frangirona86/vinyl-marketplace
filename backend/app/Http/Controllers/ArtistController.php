<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Http\Resources\ArtistResource;
use Illuminate\Http\Request;

class ArtistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $artists = Artist::with('user')->paginate(20);

        return ArtistResource::collection($artists);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'display_name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'avatar_url' => 'nullable|url',
        ]);

        $artist = Artist::create($validated);

        return (new ArtistResource($artist))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Artist $artist)
    {
        $artist->load('user');

        return new ArtistResource($artist);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Artist $artist)
    {
        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'avatar_url' => 'nullable|url',
        ]);

        $artist->update($validated);

        return new ArtistResource($artist);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Artist $artist)
    {
        $artist->delete();

        return response()->json([
            'message' => 'Artist deleted successfully'
        ], 200);
    }
}
