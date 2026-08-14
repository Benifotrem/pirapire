<?php

namespace Tests\Unit;

use App\Services\Lnurl\LnurlAuthService;
use Elliptic\EC;
use Tests\TestCase;

/**
 * NOTE: requires ext-gmp or ext-bcmath (see simplito/elliptic-php). The
 * production Docker image installs ext-gmp; a bare PHP CLI without either
 * extension will error on `new EC('secp256k1')` before these tests run.
 */
class LnurlAuthServiceTest extends TestCase
{
    private function service(): LnurlAuthService
    {
        return app(LnurlAuthService::class);
    }

    public function test_valid_signature_over_k1_is_accepted(): void
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();
        $pubHex = $keyPair->getPublic(true, 'hex');

        $k1 = bin2hex(random_bytes(32));
        $sig = $keyPair->sign($k1)->toDER('hex');

        $this->assertTrue($this->service()->verifySignature($k1, $sig, $pubHex));
    }

    public function test_signature_over_a_different_k1_is_rejected(): void
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();
        $pubHex = $keyPair->getPublic(true, 'hex');

        $k1 = bin2hex(random_bytes(32));
        $sig = $keyPair->sign($k1)->toDER('hex');

        $otherK1 = bin2hex(random_bytes(32));

        $this->assertFalse($this->service()->verifySignature($otherK1, $sig, $pubHex));
    }

    public function test_signature_from_a_different_key_is_rejected(): void
    {
        $ec = new EC('secp256k1');
        $signer = $ec->genKeyPair();
        $impostor = $ec->genKeyPair();

        $k1 = bin2hex(random_bytes(32));
        $sig = $signer->sign($k1)->toDER('hex');

        $this->assertFalse(
            $this->service()->verifySignature($k1, $sig, $impostor->getPublic(true, 'hex')),
        );
    }

    public function test_malformed_input_is_rejected_without_throwing(): void
    {
        $this->assertFalse($this->service()->verifySignature('not-hex', 'not-hex', 'not-hex'));
    }

    public function test_full_challenge_lifecycle(): void
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();
        $pubHex = $keyPair->getPublic(true, 'hex');

        $service = $this->service();
        $challenge = $service->generateChallenge();

        $session = $service->getSession($challenge['session_id']);
        $this->assertSame('pending', $session['status']);

        $sig = $keyPair->sign($challenge['k1'])->toDER('hex');
        $this->assertTrue($service->verifySignature($challenge['k1'], $sig, $pubHex));

        $this->assertTrue($service->markAuthenticated($challenge['k1'], $pubHex));

        $session = $service->getSession($challenge['session_id']);
        $this->assertSame('authenticated', $session['status']);
        $this->assertSame($pubHex, $session['linking_key']);
    }
}
