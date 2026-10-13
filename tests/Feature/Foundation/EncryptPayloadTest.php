<?php

namespace Tests\Feature\Foundation;

use App\Support\JsonResponser;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EncryptPayloadTest extends TestCase
{
    private Encrypter $encrypter;

    protected function setUp(): void
    {
        parent::setUp();

        $key = Encrypter::generateKey('aes-256-cbc');
        config([
            'apfspn.encryption.enabled' => true,
            'apfspn.encryption.key' => 'base64:'.base64_encode($key),
        ]);
        $this->encrypter = new Encrypter($key, 'aes-256-cbc');

        Route::middleware('api')->post('api/v1/_test/echo', fn (Request $request) => JsonResponser::success($request->all()));
    }

    public function test_encrypted_request_is_decrypted_and_response_is_encrypted(): void
    {
        $payload = $this->encrypter->encryptString(json_encode(['name' => 'Ada']));

        $response = $this->postJson('/api/v1/_test/echo', ['payload' => $payload])->assertOk();

        $this->assertSame(
            ['error' => false, 'message' => 'Request successful.', 'data' => ['name' => 'Ada']],
            json_decode($this->encrypter->decryptString($response->json('payload')), true),
        );
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $payload = $this->encrypter->encryptString(json_encode(['name' => 'Ada']));
        $tampered = base64_encode(str_replace('"mac":"', '"mac":"0', base64_decode($payload)));

        $this->postJson('/api/v1/_test/echo', ['payload' => $tampered])
            ->assertStatus(400)
            ->assertJson(['error' => true, 'data' => ['code' => 'invalid_payload']]);
    }

    public function test_middleware_is_inactive_when_disabled(): void
    {
        config(['apfspn.encryption.enabled' => false]);

        $this->postJson('/api/v1/_test/echo', ['name' => 'Ada'])
            ->assertExactJson(['error' => false, 'message' => 'Request successful.', 'data' => ['name' => 'Ada']]);
    }
}
