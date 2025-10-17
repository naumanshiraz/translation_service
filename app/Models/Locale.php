<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Locale extends Model
{
  use HasFactory;

  protected $fillable = ['code', 'name'];

  public function translations()
  {
    return $this->hasMany(Translation::class);
  }
}
