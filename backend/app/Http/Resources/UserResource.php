<?php

namespace App\Http\Resources;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'gym_id' => $this->gym_id,
            'gym' => $this->whenLoaded('gym', fn () => [
                'id' => $this->gym->id,
                'name' => $this->gym->name,
            ]),
            'cancellation_window_minutes' => $this->when($this->role === Role::Member, $this->cancellation_window_minutes),
            'membership_status' => $this->when($this->role === Role::Member, $this->membership_status?->value),
        ];
    }
}
