<?php

namespace App\Models;

use App\Traits\GlobalStatus;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
	use GlobalStatus;

	public function escrows()
	{
		return $this->hasMany(Escrow::class);
	}
}
