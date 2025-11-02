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
        $record = Record::with(['artist', 'variants' => function ($q) {
            $q->where('stock', '>', 0);
        }]);

        return new RecordResource($record);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreRecordRequest $request
     * @return JsonResponse
     */
    public function store(StoreRecordRequest $request): JsonResponse
    {
        // Validate the request with the StoreRecordRequest
        $record = Record::create($request->validated());
        $record->load('artist');

        return new RecordResource($record);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateRecordRequest $request
     * @param object Record $record
     * @return JsonResponse
     */
    public function update(UpdateRecordRequest $request, Record $record): JsonResponse
    {
        // Validate the request with the UpdateRecordRequest
        $record->update($request->validated());
        $record->load('artist');

        return new RecordResource($record);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param object Record $record
     * @return JsonResponse
     */
    public function destroy(Record $record): JsonResponse
    {
        $record->delete();

        return response()->json([
            'message' => 'Record deleted successfully'
        ], 200);
    }
}
