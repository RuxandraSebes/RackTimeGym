<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassRequest;
use App\Http\Resources\ClassResource;
use App\Models\GymClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClassController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ClassResource::collection(
            GymClass::where('gym_id', $request->user()->gym_id)
                ->whereNull('cancelled_at')
                ->where('starts_at', '>', now())
                ->orderBy('starts_at')
                ->get()
        );
    }

    public function store(ClassRequest $request): ClassResource
    {
        $class = GymClass::create([
            'gym_id' => $request->user()->gym_id,
            ...$this->attributesFrom($request),
        ]);

        return new ClassResource($class);
    }

    public function update(ClassRequest $request, GymClass $class): ClassResource
    {
        $this->assertEditable($request, $class);

        $class->update($this->attributesFrom($request));

        return new ClassResource($class);
    }

    public function cancel(Request $request, GymClass $class): ClassResource
    {
        $this->assertEditable($request, $class);

        $class->update(['cancelled_at' => now()]);

        return new ClassResource($class);
    }

    public function showQr(Request $request, GymClass $class): JsonResponse
    {
        abort_unless($request->user()->gym_id === $class->gym_id, 403, 'This Class belongs to a different Gym.');

        return response()->json(['qr_token' => $class->qr_token]);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributesFrom(ClassRequest $request): array
    {
        return [
            'name' => $request->string('name'),
            'starts_at' => $request->date('starts_at'),
            'capacity' => $request->integer('capacity'),
        ];
    }

    private function assertEditable(Request $request, GymClass $class): void
    {
        abort_unless($request->user()->gym_id === $class->gym_id, 403, 'This Class belongs to a different Gym.');
        abort_if($class->cancelled_at !== null, 422, 'This Class has already been cancelled.');
        abort_if($class->starts_at->isPast(), 422, 'This Class has already started.');
    }
}
