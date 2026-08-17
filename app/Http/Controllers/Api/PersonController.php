<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePersonRequest;
use App\Http\Requests\Api\UpdatePersonRequest;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->persons()->orderBy('name');

        if ($request->filled('q')) {
            $search = $request->string('q')->trim()->toString();
            $query->where('name', 'like', "%{$search}%");
        }

        return response()->json([
            'persons' => $query->get()->map->toApiArray()->values(),
        ]);
    }

    public function store(StorePersonRequest $request): JsonResponse
    {
        $person = $request->user()->persons()->create([
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->string('phone')->trim()->toString() ?: null,
        ]);

        return response()->json([
            'person' => $person->toApiArray(),
        ], 201);
    }

    public function update(UpdatePersonRequest $request, Person $person): JsonResponse
    {
        $this->authorizePerson($request, $person);

        $person->update([
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->string('phone')->trim()->toString() ?: null,
        ]);

        return response()->json([
            'person' => $person->fresh()->toApiArray(),
        ]);
    }

    public function destroy(Request $request, Person $person): JsonResponse
    {
        $this->authorizePerson($request, $person);

        if ($person->transactions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a person linked to transactions.',
            ], 422);
        }

        $person->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function authorizePerson(Request $request, Person $person): void
    {
        if ($person->user_id !== $request->user()->id) {
            abort(404);
        }
    }
}
