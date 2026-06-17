<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    use GlobalStatus;

    public function escrow()
    {
        return $this->belongsTo(Escrow::class);
    }

    public function scopeUnFunded($query)
    {
        return $query->where('payment_status', Status::MILESTONE_UNFUNDED);
    }
    public function scopeFunded($query)
    {
        return $query->where('payment_status', Status::MILESTONE_FUNDED);
    }
}
