<?php

namespace App\Models;

use App\Constants\Status;

use Illuminate\Database\Eloquent\Model;

class Escrow extends Model
{
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function disputer()
    {
        return $this->belongsTo(User::class, 'disputer_id');
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', Status::ESCROW_ACCEPTED);
    }

    public function scopeNotAccepted($query)
    {
        return $query->where('status', Status::ESCROW_NOT_ACCEPTED);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', Status::ESCROW_COMPLETED);
    }

    public function scopeDisputed($query)
    {
        return $query->where('status', Status::ESCROW_DISPUTED);
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', Status::ESCROW_CANCELLED);
    }

    public function scopeCheckUser($query)
    {
        return $query->where(function ($q) {
            $q->orWhere('buyer_id', auth()->id())->orWhere('seller_id', auth()->id());
        });
    }

    public function restAmount()
    {
        return $this->amount + $this->buyer_charge - $this->paid_amount;
    }

    public function getEscrowStatusAttribute()
    {

        if ($this->status == Status::ESCROW_NOT_ACCEPTED) {
            $html = '<span class="badge badge--dark">' . trans("Not Accepted") . '</span>';
        } elseif ($this->status == Status::ESCROW_COMPLETED) {
            $html = '<span class="badge badge--success">' . trans("Completed") . '</span>';
        } elseif ($this->status == Status::ESCROW_ACCEPTED) {
            $html = '<span class="badge badge--primary">' . trans("Accepted") . '</span>';
        } elseif ($this->status == Status::ESCROW_DISPUTED) {
            $html = '<span class="badge badge--danger">' . trans("Disputed") . '</span>';
        } else {
            $html = '<span class="badge badge--warning">' . trans("Cancelled") . '</span>';
        }

        return $html;
    }
}
