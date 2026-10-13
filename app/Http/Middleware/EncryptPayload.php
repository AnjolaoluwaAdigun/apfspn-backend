<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

class EncryptPayload
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('apfspn.encryption.enabled') || $this->bypassed($request)) {
            return $next($request);
        }

        $encrypter = $this->encrypter();

        if ($request->filled('payload')) {
            $request->replace($this->decrypt($encrypter, (string) $request->input('payload')));
        }

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setData(['payload' => $encrypter->encryptString($response->getContent())]);
        }

        return $response;
    }

    private function decrypt(Encrypter $encrypter, string $payload): array
    {
        try {
            $data = json_decode($encrypter->decryptString($payload), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw new ApiException('The request payload could not be decrypted.', 400, ['code' => 'invalid_payload']);
        }

        return is_array($data) ? $data : [];
    }

    private function bypassed(Request $request): bool
    {
        return $request->is(...config('apfspn.encryption.except', [])) || $request->hasValidSignature();
    }

    private function encrypter(): Encrypter
    {
        $key = (string) config('apfspn.encryption.key');

        if (Str::startsWith($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'));
        }

        return new Encrypter($key, config('apfspn.encryption.cipher'));
    }
}
