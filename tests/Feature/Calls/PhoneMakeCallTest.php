<?php
namespace Tests\Feature\Calls;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\PhoneNumber;
use App\Models\CallLog;
use Twilio\Rest\Client;
use Twilio\Rest\Verify\V2\Service\VerificationCheckList;
use Twilio\Rest\Api\V2010\Account\CallList;
use Twilio\Exceptions\TwilioException;
use Mockery;

class PhoneMakeCallTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $twilioMock;
    protected $callListMock;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'twilio.verify_service_sid' => 'VA_TEST_SID',
            'twilio.sid' => 'AC_TEST_SID',
            'twilio.auth_token' => 'TEST_AUTH_TOKEN',
        ]);
        $this->user = User::factory()->create();
        $this->twilioMock = Mockery::mock(Client::class);
        $this->callListMock = Mockery::mock(CallList::class);
        $this->verificationCheckListMock = Mockery::mock(VerificationCheckList::class);
        $this->twilioMock->calls = $this->callListMock;
        $this->verificationCheckListMock->shouldReceive('create')
            ->andReturn((object) ['status' => 'approved']);
        $this->app->instance(Client::class, $this->twilioMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Tests para makeCall ---
    public function test_authenticated_user_can_make_call_with_verified_number()
    {
        $userPhoneNumber = '+15551112222';
        $destinationNumber = '+15553334444';
        $callSid = 'CA_TEST_SID';
        $verifiedNumber = PhoneNumber::factory()->create([
            'user_id' => $this->user->id,
            'number' => $userPhoneNumber,
            'verified_at' => now(),
        ]);
        $callMock = (object) [
            'sid' => $callSid,
            'status' => 'initiated',
        ];
        $callsMock = Mockery::mock();
        $callsMock->shouldReceive('create')
            ->once()
            ->with(
                $destinationNumber,
                $userPhoneNumber,
                Mockery::on(function ($arg) {
                    return is_array($arg) &&
                        isset($arg['url']) &&
                        isset($arg['statusCallback']) &&
                        $arg['method'] === 'POST';
                })
            )
            ->andReturn($callMock);
        $twilioClientMock = Mockery::mock(Client::class);
        $twilioClientMock->calls = $callsMock;
        $this->app->instance(Client::class, $twilioClientMock);

        $response = $this->actingAs($this->user)
            ->postJson('/api/call', ['destination_number' => $destinationNumber]);
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Call initiated.',
                'call_sid' => $callSid,
            ]);
        $this->assertDatabaseHas('call_logs', [
            'user_id' => $this->user->id,
            'phone_number_id' => $verifiedNumber->id,
            'destination_number' => $destinationNumber,
            'twilio_call_sid' => $callSid,
            'status' => 'initiated',
        ]);
    }
    public function test_call_initiation_requires_authentication()
    {
        $response = $this->postJson('/api/call', [
            'destination_number' => '+15553334444'
        ]);

        $response->assertStatus(401);
    }
    public function test_call_initiation_requires_destination_number()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/call', [
                // 'destination_number' is missing
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['destination_number']);
    }
    public function test_call_initiation_fails_if_user_phone_not_registered()
    {

        $destinationNumber = '+15553334444';

        $response = $this->actingAs($this->user)
            ->postJson('/api/call', [
                'destination_number' => $destinationNumber
            ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'User phone number not registered or verified.']);
        $this->callListMock->shouldNotHaveReceived('create');
        $this->assertDatabaseMissing('call_logs', [
            'user_id' => $this->user->id,
        ]);
    }
    public function test_call_initiation_fails_if_user_phone_not_verified()
    {
        $userPhoneNumber = '+15551112222';
        $destinationNumber = '+15553334444';

        PhoneNumber::factory()->create([
            'user_id' => $this->user->id,
            'number' => $userPhoneNumber,
            'verified_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/call', [
                'destination_number' => $destinationNumber
            ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'User phone number not registered or verified.']);

        $this->callListMock->shouldNotHaveReceived('create');
        $this->assertDatabaseMissing('call_logs', [
            'user_id' => $this->user->id,
        ]);

    }
    public function test_call_initiation_handles_twilio_error()
    {
        $userPhoneNumber = '+15551112222';
        $destinationNumber = '+15553334444';

        // Crear número verificado asociado al usuario
        $verifiedNumber = PhoneNumber::factory()->create([
            'user_id' => $this->user->id,
            'number' => $userPhoneNumber,
            'verified_at' => now(),
        ]);

        // Mockear Twilio para que lance una excepción al crear la llamada
        $this->callListMock->shouldReceive('create')
            ->once()
            ->andThrow(new TwilioException('Cannot call this number'));



        $response = $this->actingAs($this->user)
            ->postJson('/api/call', [
                'destination_number' => $destinationNumber
            ]);

        $response->assertStatus(500)
            ->assertJsonFragment(['message' => 'Could not initiate call: Cannot call this number']);


        $this->assertDatabaseHas('call_logs', [
            'user_id' => $this->user->id,
            'destination_number' => $destinationNumber,
            'status' => 'failed',
            'error_message' => 'Cannot call this number',
        ]);
    }
    // --- Tests para getUserPhoneNumber ---
    public function test_authenticated_user_can_get_their_phone_number_status()
    {
        // not number registered
        $response = $this->actingAs($this->user)
            ->getJson('/api/user/phone');

        $response->assertStatus(200)
            ->assertJson([
                'phone_number' => null,
                'is_verified' => false,
            ]);

        // number registered but not verified
        PhoneNumber::where('user_id', $this->user->id)->delete();
        $phoneNumber = '+15559998888';
        PhoneNumber::create([
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);
        $this->user->refresh();
        $response = $this->actingAs($this->user)
            ->getJson('/api/user/phone');

        $response->assertStatus(200)
            ->assertJson([
                'phone_number' => $phoneNumber,
                'is_verified' => false,
            ]);
        // number registered and verified
        PhoneNumber::where('user_id', $this->user->id)->delete();

        PhoneNumber::create([
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => now(),
        ]);
        $this->user->refresh();

        $response = $this->actingAs($this->user)
            ->getJson('/api/user/phone');

        $response->assertStatus(200)
            ->assertJson([
                'phone_number' => $phoneNumber,
                'is_verified' => true,
            ]);

    }

    // --- Tests para Webhooks (generateTwiML) ---
    public function test_twilio_webhook_generates_twiml_to_dial_destination_number()
    {
        $destinationNumber = '+15553334444';
        $response = $this->postJson('/api/twilio/voice/twiml', [
            'To' => $destinationNumber
        ]);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $expectedTwiML = '<Response><Dial>' . $destinationNumber . '</Dial></Response>';
        $this->assertStringContainsStringIgnoringCase($expectedTwiML, $response->getContent());
    }
    public function test_twilio_webhook_generates_twiml_with_error_message_if_destination_missing()
    {
        $response = $this->postJson('/api/twilio/voice/twiml', [
            'To' => null
        ]);
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $expectedTwiML = '<Response><Say>Error en la configuración de la llamada. Por favor, inténtelo de nuevo más tarde.</Say></Response>';
        $this->assertStringContainsStringIgnoringCase($expectedTwiML, $response->getContent());
    }
    // --- Tests para Webhooks (handleCallStatusCallback) ---   
    public function test_twilio_webhook_updates_call_log_status()
    {
        $callSid = 'CA_TEST_SID';
        $destinationNumber = '+15553334444';

        $callLog = CallLog::create([
            'user_id' => $this->user->id,
            'destination_number' => $destinationNumber,
            'twilio_call_sid' => $callSid,
            'status' => 'initiated',
        ]);

        $response = $this->postJson('/api/twilio/voice/status-callback', [
            'CallSid' => $callSid,
            'CallStatus' => 'ringing',
            'To' => $destinationNumber,
            'From' => '+15551112222',
            'AccountSid' => 'AC_TEST_SID',
            'ApiVersion' => '2010-04-01',
        ]);

        $response->assertStatus(200);
        $callLog->refresh();
        $this->assertEquals('ringing', $callLog->status);
    }


}