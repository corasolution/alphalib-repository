<?php

namespace Alpha\Journal\Http\Controllers;

use Alpha\Journal\Ror\RorClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RorController extends Controller
{
    public function search(Request $request, RorClient $client): JsonResponse
    {
        $results = $client->search((string) $request->query('q', ''));

        return response()->json(array_map(fn ($org) => [
            'ror_id' => $org->rorId,
            'name' => $org->name,
            'country' => $org->country,
        ], $results));
    }
}
