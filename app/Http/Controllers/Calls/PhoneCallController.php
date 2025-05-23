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

class PhoneCallController extends Controller
{

    protected $twilioClient;

    public function __construct(Client $twilioClient)
    {
        $this->twilioClient = $twilioClient;
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
        $fromNumber = $usePhoneNumberTwilio ? config('services.twilio.from') : $phoneRecord->number;
        $toNumber = $request->destination_number;

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






