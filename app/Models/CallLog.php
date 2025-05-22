<?php


namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'destination_number',
        'twilio_call_sid',
        'status',
        'start_time',
        'end_time',
        'duration',
        'price',
        'error_message',
        'phone_number_id',

    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Relation: a call log belongs to a user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function phoneNumber()
    {
        return $this->belongsTo(PhoneNumber::class);
    }

}
