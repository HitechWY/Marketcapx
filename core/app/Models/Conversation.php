<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model {
    use GlobalStatus;

    public function escrow() {
        return $this->belongsTo(Escrow::class);
    }

    public function messages() {
        return $this->hasMany(Message::class);
    }

    public function scopeCheckUser($query) {
        return $query->where(function ($q) {
            $q->orWhere('buyer_id', auth()->id())->orWhere('seller_id', auth()->id());
        });
    }
}
