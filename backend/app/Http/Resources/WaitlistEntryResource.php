<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WaitlistEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'class' => $this->whenLoaded('gymClass', fn () => [
                'id' => $this->gymClass->id,
                'name' => $this->gymClass->name,
                'starts_at' => $this->gymClass->starts_at->toIso8601String(),
            ]),
            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'name' => $this->member->name,
            ]),
            'position' => $this->position(),
            'offered_at' => $this->offered_at?->toIso8601String(),
            'offer_expires_at' => $this->offer_expires_at?->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
        ];
    }
}
