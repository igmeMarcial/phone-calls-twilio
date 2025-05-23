<?php


use App\Http\Controllers\Calls\PhoneCallController;
use Illuminate\Support\Facades\Route;



Route::middleware('auth')->prefix('api')->group(function () {
    Route::post('/user/phone/register-request', [PhoneCallController::class, 'registerAndRequestVerification']);
    Route::post('/user/phone/verify', [PhoneCallController::class, 'verifyPhoneNumber']);
    Route::post('/call', [PhoneCallController::class, 'makeCall']);
    Route::get('/user/phone', [PhoneCallController::class, 'getUserPhoneNumber']);
    Route::get('/user/call-logs', [PhoneCallController::class, 'getUserCallLogs']);
    Route::post('/user/phone/delete', [PhoneCallController::class, 'deletePhoneNumber']);
});
Route::post('/twilio/voice/status-callback', [PhoneCallController::class, 'handleCallStatusCallback'])->name('twilio.voice.status-callback');
Route::any('/twilio/voice/twiml', [PhoneCallController::class, 'generateTwiML'])->name('twilio.voice.twiml'); // Twilio puede usar GET o POST
