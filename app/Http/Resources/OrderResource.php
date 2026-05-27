<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'date' => $this->date,
            'user' => $this->user ?? null,
            'state' => [
                'id' => $this->state->id,
                'name' => $this->state->name,
                'color' => $this->state->color,
            ],
        ];
    }
}
