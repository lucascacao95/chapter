<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KindleDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'status' => $this->status, 'error_code' => $this->error_code, 'submitted_at' => $this->submitted_at];
    }
}
