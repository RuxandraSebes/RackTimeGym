<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gym_id' => $this->gym_id,
            'name' => $this->name,
            'starts_at' => $this->starts_at->toIso8601String(),
            'capacity' => $this->capacity,
            'remaining_capacity' => $this->remaining_capacity,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
        ];
    }
}
