<?php

namespace App\Http\Controllers\Calls;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\PhoneNumber;
use App\Models\CallLog;
use Twilio\Rest\Client;
use Twilio\TwiML\VoiceResponse;
use Twilio\Exceptions\TwilioException;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Twilio\Jwt\AccessToken;
use Twilio\Jwt\Grants\VoiceGrant;

class PhoneCallController extends Controller
{

    protected $twilioClient;

    public function __construct(Client $twilioClient)
    {
        $this->twilioClient = $twilioClient;
    }
    public function generateVoiceToken(Request $request)
    {
        $user = Auth::user();
        $phoneRecord = $user->phoneNumber;

        if (!$phoneRecord || !$phoneRecord->is_verified) {
            return response()->json(['message' => 'User phone number not registered or verified.'], 400);
        }
        $accountSid = config('services.twilio.sid');
        $apiKeySid = config('services.twilio.api_key_sid');
        $apiKeySecret = config('services.twilio.api_key_secret');
        $appSid = config('services.twilio.twiml_app_sid');
        if (!$accountSid || !$apiKeySid || !$apiKeySecret || !$appSid) {
            return response()->json(['message' => 'Twilio Voice configuration incomplete.'], 500);
        }

        try {
            $identity = 'user_' . $user->id;
            $token = new AccessToken($accountSid, $apiKeySid, $apiKeySecret, 3600, $identity);
            $voiceGrant = new VoiceGrant();
            $voiceGrant->setOutgoingApplicationSid($appSid);
            $voiceGrant->setIncomingAllow(true);

            $token->addGrant($voiceGrant);
            Log::info('Voice token generated');
            return response()->json([
                'token' => $token->toJWT(),
                'identity' => $identity
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error generating voice token: ' . $e->getMessage());
            return response()->json(['message' => 'Error generating token: ' . $e->getMessage()], 500);
        }

    }
    public function handleOutgoingCall(Request $request)
    {
        $to = $request->input('To');
        $from = $request->input('From');

        Log::info('Iniciando llamada');
        Log::info('Outgoing call request', ['to' => $to, 'from' => $from]);


        $identityPart = str_replace('client:', '', $from);
        $userId = str_replace('user_', '', $identityPart);

        if (!is_numeric($userId)) {
            Log::error('Outgoing call error: Invalid user ID format in "From" parameter.', ['from' => $from, 'extracted_id' => $userId]);
            $response = new VoiceResponse();
            $response->say('Error: Usuario no encontrado.');
            return response($response, 200)->header('Content-Type', 'text/xml');
        }
        $user = \App\Models\User::find((int) $userId);
        if (!$user) {
            Log::error('Outgoing call error: User not found for extracted ID ' . $userId);
            $response = new VoiceResponse();
            $response->say('Error: Usuario no encontrado.');
            return response($response, 200)->header('Content-Type', 'text/xml');
        }
        $phoneRecord = $user->phoneNumber;
        if (!$phoneRecord || !$phoneRecord->is_verified) {
            $response = new VoiceResponse();
            $response->say('Error: Número de teléfono no verificado.');
            return response($response, 200)->header('Content-Type', 'text/xml');
        }
        $callLog = CallLog::create([
            'user_id' => $user->id,
            'phone_number_id' => $phoneRecord->id,
            'destination_number' => $to,
            'status' => 'initiated',
            'twilio_call_sid' => $request->input('CallSid'),
        ]);

        $response = new VoiceResponse();

        $usePhoneNumberTwilio = config('services.twilio.use_phone_number');
        $twilioFromNumber = config('services.twilio.from');
        $callerId = $usePhoneNumberTwilio && !empty($twilioFromNumber) ? $twilioFromNumber : $phoneRecord->number;

        $response->dial($to, ['callerId' => $callerId]);

        Log::info('Outgoing call TwiML generated', [
            'user_id' => $user->id,
            'to' => $to,
            'caller_id' => $callerId,
            'call_log_id' => $callLog->id
        ]);

        return response($response, 200)->header('Content-Type', 'text/xml');
    }
    public function handleIncomingCall(Request $request)
    {
        $from = $request->input('From');
        $to = $request->input('To');

        Log::info('Incoming call', ['from' => $from, 'to' => $to]);

        $response = new VoiceResponse();

        $phoneRecord = PhoneNumber::where('number', $to)->where('verified_at', '!=', null)->first();

        if ($phoneRecord) {
            $identity = 'user_' . $phoneRecord->user_id;

            $client = $response->dial(null, ['timeout' => 30]);
            $client->client($identity);

            $response->say('Lo sentimos, el usuario no está disponible en este momento.');
        } else {
            $response->say('Número no válido.');
        }

        return response($response, 200)->header('Content-Type', 'text/xml');
    }


    public function registerAndRequestVerification(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        $user = Auth::user();
        $phoneNumber = $request->phone_number;

        $verifyServiceSid = config('services.twilio.verify_service_sid');
        if (empty($verifyServiceSid)) {
            return response()->json(['message' => 'Twilio Verify service not configured.'], 500);
        }

        try {
            $verification = $this->twilioClient->verify->v2->services($verifyServiceSid)
                ->verifications
                ->create($phoneNumber, "sms");

            $phoneRecord = PhoneNumber::updateOrCreate(
                ['user_id' => $user->id],
                ['number' => $phoneNumber, 'verified_at' => null]
            );
            return response()->json([
                'message' => 'Verification code sent successfully.',
                'verification_sid' => $verification->sid,
                'phone_number' => $phoneRecord->number
            ], 200);

        } catch (TwilioException $e) {
            return response()->json(['message' => 'Could not send verification code: ' . $e->getMessage()], 500);
        }
    }

    public function verifyPhoneNumber(Request $request)
    {
        $request->validate([
            'code' => 'required|string|digits:6',
        ]);

        $user = Auth::user();
        $phoneRecord = $user->phoneNumber;


        if (!$phoneRecord) {
            return response()->json(['message' => 'No phone number registered for this user.'], 400);
        }

        $phoneNumber = $phoneRecord->number;
        $code = $request->code;

        $verifyServiceSid = config('services.twilio.verify_service_sid');

        if (!$verifyServiceSid) {
            return response()->json(['message' => 'Twilio Verify service not configured.'], 500);
        }

        try {
            $verification_check = $this->twilioClient->verify->v2->services($verifyServiceSid)
                ->verificationChecks
                ->create(['to' => $phoneNumber, 'code' => $code]);
            if ($verification_check->status === 'approved') {
                $phoneRecord->verified_at = now();
                $phoneRecord->save();
                return response()->json(['message' => 'Phone number verified successfully.'], 200);
            } else {
                return response()->json(['message' => 'Invalid verification code.'], 400);
            }
        } catch (TwilioException $e) {
            return response()->json(['message' => 'Verification failed: ' . $e->getMessage()], 500);
        }
    }

    public function makeCall(Request $request)
    {
        $request->validate([
            'destination_number' => 'required|string',
        ]);
        $user = Auth::user();
        $phoneRecord = $user->phoneNumber;

        if (!$phoneRecord || !$phoneRecord->is_verified) {
            return response()->json(['message' => 'User phone number not registered or verified.'], 400);
        }

        $usePhoneNumberTwilio = config('services.twilio.use_phone_number');
        $twilioFromNumber = config('services.twilio.from');
        $fromNumber = $usePhoneNumberTwilio && !empty($twilioFromNumber) ? $twilioFromNumber : $phoneRecord->number;
        $toNumber = $request->destination_number;
        Log::info('Making call from ' . $fromNumber);
        $callLog = CallLog::create([
            'user_id' => $user->id,
            'phone_number_id' => $phoneRecord->id,
            'destination_number' => $request->destination_number,
            'status' => 'initiated',
        ]);

        $twimlUrl = route('twilio.voice.twiml');
        $statusCallbackUrl = route('twilio.voice.status-callback');

        try {
            $call = $this->twilioClient->calls->create(
                $toNumber,
                $fromNumber,
                [
                    'url' => $twimlUrl,
                    'method' => 'POST',
                    'statusCallback' => $statusCallbackUrl,
                    'statusCallbackMethod' => 'POST',
                    'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed', 'failed', 'busy', 'no-answer'],
                ]
            );


            $callLog->twilio_call_sid = $call->sid;
            $callLog->status = $call->status;
            $callLog->save();

            Log::info("Call initiated successfully via Twilio tmr.", [
                'user_id' => $user->id,
                'from' => $fromNumber,
                'to' => $toNumber,
                'call_sid' => $call->sid,
                'log_id' => $callLog->id
            ]);

            return response()->json(['message' => 'Call initiated.', 'call_sid' => $call->sid, 'log_id' => $callLog->id], 200);

        } catch (TwilioException $e) {

            $callLog->status = 'failed';
            $callLog->error_message = $e->getMessage();
            $callLog->save();

            return response()->json([
                'message' => 'Could not initiate call: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancelCall(Request $request, $callSid)
    {
        try {
            $this->twilioClient->calls($callSid)->update(['status' => 'completed']);
            return response()->json(['message' => 'Call cancelled.'], 200);
        } catch (TwilioException $e) {
            return response()->json(['message' => 'Could not cancel call: ' . $e->getMessage()], 500);
        }
    }

    public function getUserPhoneNumber(Request $request)
    {
        $user = Auth::user();
        $phoneRecord = $user->phoneNumber;

        if ($phoneRecord) {
            return response()->json([
                'id' => $phoneRecord->id,
                'phone_number' => $phoneRecord->number,
                'is_verified' => $phoneRecord->is_verified,
                'verified_at' => $phoneRecord->verified_at ? $phoneRecord->verified_at->toDateTimeString() : null,
            ], 200);
        } else {
            return response()->json(['phone_number' => null, 'is_verified' => false], 200);
        }
    }
    public function deletePhoneNumber(Request $request)
    {
        $user = Auth::user();
        $phoneRecord = $user->phoneNumber;

        if ($phoneRecord) {
            $phoneRecord->delete();
            return response()->json(['message' => 'Phone number deleted.'], 200);
        } else {
            return response()->json(['message' => 'No phone number to delete.'], 200);
        }
    }

    public function handleCallStatusCallback(Request $request)
    {
        $callSid = $request->input('CallSid');
        $callStatus = $request->input('CallStatus');
        $duration = $request->input('CallDuration');
        $startTime = $request->input('StartTime');
        $endTime = $request->input('EndTime');
        $price = $request->input('Price');
        $errorMessage = $request->input('ErrorMessage');
        $parentCallSid = $request->input('ParentCallSid');

        Log::info('Call status callback received', [
            'CallSid' => $callSid,
            'CallStatus' => $callStatus,
            'Duration' => $duration
        ]);

        $callLog = CallLog::where('twilio_call_sid', $callSid)->first();
        if (!$callLog && $parentCallSid) {
            $callLog = CallLog::where('twilio_call_sid', $parentCallSid)->first();
            if ($callLog) {
                Log::info("Found CallLog using ParentCallSid.", ['CallSid' => $callSid, 'ParentCallSid' => $parentCallSid, 'log_id' => $callLog->id]);
            }
        }

        if ($callLog) {
            $callLog->status = $callStatus;
            if ($startTime) {
                try {
                    $callLog->start_time = \Carbon\Carbon::parse($startTime);
                } catch (\Exception $e) {
                }
            }
            if ($endTime) {
                try {
                    $callLog->end_time = \Carbon\Carbon::parse($endTime);
                } catch (\Exception $e) {
                }
            }

            if ($duration !== null) {
                $callLog->duration = (int) $duration;
            }
            if ($price !== null) {
                $callLog->price = (float) $price;
            }
            if ($errorMessage !== null) {
                $callLog->error_message = $errorMessage;
            }

            $callLog->save();

        } else {
            Log::warning("Received Twilio Status Callback for unknown CallSid.", ['CallSid' => $callSid, 'ParentCallSid' => $parentCallSid, 'request_params' => $request->all()]);
        }

        return response()->json(['message' => 'Callback received'], 200);
    }



    public function generateTwiML(Request $request)
    {
        $response = new VoiceResponse();
        $destinationNumber = $request->input('To');

        if ($destinationNumber) {
            $response->dial($destinationNumber);
        } else {
            $response->say('Error en la configuración de la llamada. Por favor, inténtelo de nuevo más tarde.');
        }

        return response($response, 200)->header('Content-Type', 'text/xml');
    }

    public function getUserCallLogs()
    {
        $user = Auth::user();
        $logs = CallLog::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();
        return response()->json($logs);
    }

}






