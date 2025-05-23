<?php

namespace Tests\Feature\Calls;

use Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\PhoneNumber;
use Twilio\Rest\Client;
use Twilio\Rest\Verify\V2\Service\VerificationList;
use Twilio\Rest\Verify\V2\Service\VerificationCheckList;
use Twilio\Rest\Verify\V2\Service\VerificationInstance;
use Twilio\Rest\Verify\V2\Service\VerificationCheckInstance;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Twilio\Exceptions\TwilioException;


use Mockery;

class PhoneRegisterTest extends TestCase
{

    use RefreshDatabase;


    protected $user;
    protected $twilioMock;
    protected $verifyServiceMock;
    protected $verificationListMock;
    protected $verificationCheckListMock;

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

        $this->verificationListMock = Mockery::mock(VerificationList::class);
        $this->verificationCheckListMock = Mockery::mock(VerificationCheckList::class);

        $verifyServiceMock = Mockery::mock(ServiceContext::class);
        $verifyV2Mock = Mockery::mock(V2::class);
        $verifyRootMock = Mockery::mock(Verify::class);

        $verifyServiceMock->verifications = $this->verificationListMock;
        $verifyServiceMock->verificationChecks = $this->verificationCheckListMock;
        $this->verifyServiceMock = $verifyServiceMock;

        $verifyV2Mock->shouldReceive('services')
            ->with(config('twilio.verify_service_sid'))
            ->andReturn($verifyServiceMock);
        $verifyRootMock->v2 = $verifyV2Mock;
        $this->twilioMock->verify = $verifyRootMock;
        $this->app->instance(Client::class, $this->twilioMock);
    }
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // --- Tests para registerAndRequestVerification ---
    public function test_authenticated_user_can_register_phone_number_and_request_verification()
    {
        $phoneNumber = '+15551234567';
        $verificationInstanceMock = Mockery::mock(VerificationInstance::class);
        $verificationInstanceMock->sid = 'VE_TEST_SID';

        $this->verificationListMock->shouldReceive('create')
            ->once()
            ->with($phoneNumber, 'sms')
            ->andReturn($verificationInstanceMock);

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/register-request', [
                'phone_number' => $phoneNumber
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification code sent successfully.',
                'phone_number' => $phoneNumber
            ]);


        $this->assertDatabaseHas('phone_numbers', [
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);

        $this->verifyMockeryExpectations();
    }
    protected function verifyMockeryExpectations()
    {
        $this->verificationListMock->shouldHaveReceived('create')->once();
    }

    public function test_phone_number_registration_requires_authentication()
    {
        $response = $this->postJson('/api/user/phone/register-request', [
            'phone_number' => '+15551234567'
        ]);

        $response->assertStatus(401);
    }
    public function test_phone_number_registration_requires_phone_number()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/register-request', [
                // 'phone_number' is missing
            ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number']);

        $this->verificationListMock->shouldNotHaveReceived('create');
    }
    public function test_phone_number_registration_handles_twilio_error()
    {
        $phoneNumber = '+15551234567';
        $this->verificationListMock->shouldReceive('create')
            ->once()
            ->andThrow(new TwilioException('Invalid phone number format'));

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/register-request', [
                'phone_number' => $phoneNumber
            ]);

        $response->assertStatus(500)
            ->assertJsonFragment(['message' => 'Could not send verification code: Invalid phone number format']);

        $this->assertDatabaseMissing('phone_numbers', [
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
        ]);
    }
    public function test_phone_number_registration_fails_if_verify_sid_not_configured()
    {
        Config::set('services.twilio.verify_service_sid', '');

        $phoneNumber = '+15551234567';

        $this->verificationListMock->shouldNotReceive('create');

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/register-request', [
                'phone_number' => $phoneNumber
            ]);

        $response->assertStatus(500)
            ->assertJsonFragment(['message' => 'Twilio Verify service not configured.']);

        $this->assertDatabaseMissing('phone_numbers', [
            'user_id' => $this->user->id,
        ]);
    }
    // --- Tests para verifyPhoneNumber ---
    public function test_authenticated_user_can_verify_phone_number_with_correct_code()
    {
        $phoneNumber = '+15551234567';

        // registro previo no verificado
        PhoneNumber::create([
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);

        $verificationCode = '123456';
        $verificationCheckInstanceMock = Mockery::mock(VerificationCheckInstance::class);
        $verificationCheckInstanceMock->status = 'approved';

        $this->verificationCheckListMock->shouldReceive('create')
            ->once()
            ->with(['to' => $phoneNumber, 'code' => $verificationCode])
            ->andReturn($verificationCheckInstanceMock);

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/verify', [
                'code' => $verificationCode,
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Phone number verified successfully.']);

        $this->assertDatabaseHas('phone_numbers', [
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
        ]);

        $this->assertNotNull(PhoneNumber::where('user_id', $this->user->id)->first()->verified_at);
    }
    public function test_phone_number_verification_requires_authentication()
    {
        $response = $this->postJson('/api/user/phone/verify', [
            'code' => '123456'
        ]);
        $response->assertStatus(401);
    }
    public function test_phone_number_verification_requires_code()
    {
        PhoneNumber::create([
            'user_id' => $this->user->id,
            'number' => '+15551234567',
            'verified_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/verify', [
                // 'code' is missing
            ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }


    public function test_phone_number_verification_fails_with_incorrect_code()
    {
        $phoneNumber = '+15551234567';
        PhoneNumber::create([
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);

        $verificationCode = '654321'; // Códe errónico

        $verificationCheckInstanceMock = Mockery::mock(VerificationCheckInstance::class);
        $verificationCheckInstanceMock->status = 'pending';

        $this->verificationCheckListMock->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'to' => $phoneNumber,
                'code' => $verificationCode,
            ]))
            ->andReturn($verificationCheckInstanceMock);

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/verify', [
                'code' => $verificationCode,
            ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Invalid verification code.']);
        $this->assertDatabaseHas('phone_numbers', [
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);
    }

    public function test_phone_number_verification_fails_if_no_phone_number_registered()
    {

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/verify', [
                'code' => '123456',
            ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'No phone number registered for this user.']);
        $this->verificationCheckListMock->shouldNotHaveReceived('create');
    }

    public function test_phone_number_verification_handles_twilio_error()
    {
        $phoneNumber = '+15551234567';
        PhoneNumber::create([
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);

        $verificationCode = '123456';

        $this->verificationCheckListMock->shouldReceive('create')
            ->once()
            ->andThrow(new TwilioException('Verification check failed'));

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/verify', [
                'code' => $verificationCode,
            ]);

        $response->assertStatus(500)
            ->assertJsonFragment(['message' => 'Verification failed: Verification check failed']);
        $this->assertDatabaseHas('phone_numbers', [
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);
    }

    public function phone_number_verification_fails_if_verify_sid_not_configured()
    {
        Config::set('twilio.verify_service_sid', '');
        $phoneNumber = '+15551234567';
        PhoneNumber::create([
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);

        $verificationCode = '123456';

        $this->verificationCheckListMock->shouldNotReceive('create');

        $response = $this->actingAs($this->user)
            ->postJson('/api/user/phone/verify', [
                'code' => $verificationCode
            ]);

        $response->assertStatus(500)
            ->assertJsonFragment(['message' => 'Twilio Verify service not configured.']);

        $this->assertDatabaseHas('phone_numbers', [
            'user_id' => $this->user->id,
            'number' => $phoneNumber,
            'verified_at' => null,
        ]);
    }

}

