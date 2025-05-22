<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'number',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    // Relation: a number belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Verified if verified_at is not null
    public function getIsVerifiedAttribute()
    {
        return $this->verified_at !== null;
    }
}
