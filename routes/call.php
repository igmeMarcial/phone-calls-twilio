<?php


use App\Http\Controllers\Calls\PhoneCallController;
use Illuminate\Support\Facades\Route;



Route::middleware('auth')->prefix('api')->group(function () {
    Route::post('/user/phone/register-request', [PhoneCallController::class, 'registerAndRequestVerification']);
    Route::post('/user/phone/verify', [PhoneCallController::class, 'verifyPhoneNumber']);
    // Route::post('/call', [PhoneCallController::class, 'makeCall']);
    Route::get('/user/phone', [PhoneCallController::class, 'getUserPhoneNumber']);
    Route::get('/user/call-logs', [PhoneCallController::class, 'getUserCallLogs']);
    Route::post('/user/phone/delete', [PhoneCallController::class, 'deletePhoneNumber']);
    Route::post('/call/{callSid}/end', [PhoneCallController::class, 'cancelCall']);
    Route::get('/voice/token', [PhoneCallController::class, 'generateVoiceToken']);
});
Route::post('/twilio/voice/status-callback', [PhoneCallController::class, 'handleCallStatusCallback'])->withoutMiddleware('web')->name('twilio.voice.status-callback');
Route::any('/twilio/voice/twiml', [PhoneCallController::class, 'generateTwiML'])->withoutMiddleware('web')->name('twilio.voice.twiml');

// routes para las llamadas
Route::any('/twilio/voice/outgoing', [PhoneCallController::class, 'handleOutgoingCall'])->withoutMiddleware('web')->name('twilio.voice.outgoing');
Route::any('/twilio/voice/incoming', [PhoneCallController::class, 'handleIncomingCall'])->withoutMiddleware('web')->name('twilio.voice.incoming');
