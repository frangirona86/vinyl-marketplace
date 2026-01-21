<?php

namespace App\Http\Controllers;

use App\Models\Record;
use App\Http\Requests\StoreRecordRequest;
use App\Http\Requests\UpdateRecordRequest;
use App\Http\Resources\RecordResource;
use App\Http\Resources\RecordCollection;


class RecordController extends Controller
{
    public function index()
    {
        $records = Record::with(['artist', 'variants' => function ($q) {
            $q->where('stock', '>', 0);
        }])->paginate(20);

        return RecordResource::collection($records);
    }

    public function show(Record $record): RecordResource
    {
        $record->load(['artist', 'variants' => function ($q) {
            $q->where('stock', '>', 0);
        }]);

        return new RecordResource($record);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRecordRequest $request)
    {
        $record = Record::create($request->validated());
        $record->load('artist');

        return (new RecordResource($record))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRecordRequest $request, Record $record): RecordResource
    {
        $record->update($request->validated());
        $record->load('artist');

        return new RecordResource($record);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Record $record)
    {
        $record->delete();

        return response()->json([
            'message' => 'Record deleted successfully'
        ], 200);
    }
}
