<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderTypeWasteResource extends JsonResource
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
            'type_waste_id' => $this->type_waste_id,
            'name' => $this->type_waste,
            'weight' => (float) $this->weight,
            'points' => (float) $this->points,
        ];
    }
}
