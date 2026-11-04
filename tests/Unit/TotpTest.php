<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** RFC 6238 Appendix B test vectors (SHA-1), truncated to 6 digits. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // "12345678901234567890"

    public function test_matches_rfc_6238_reference_codes(): void
    {
        $this->assertSame('287082', Totp::codeAt(self::RFC_SECRET, Totp::currentStep(59)));
        $this->assertSame('081804', Totp::codeAt(self::RFC_SECRET, Totp::currentStep(1111111109)));
        $this->assertSame('050471', Totp::codeAt(self::RFC_SECRET, Totp::currentStep(1111111111)));
        $this->assertSame('005924', Totp::codeAt(self::RFC_SECRET, Totp::currentStep(1234567890)));
    }

    public function test_base32_round_trip(): void
    {
        $this->assertSame(self::RFC_SECRET, Totp::base32Encode('12345678901234567890'));
        $this->assertSame('12345678901234567890', Totp::base32Decode(self::RFC_SECRET));
        $this->assertSame(32, strlen(Totp::generateSecret()));
    }

    public function test_verify_allows_one_step_of_drift_and_blocks_replay(): void
    {
        $time = 1111111109;
        $step = Totp::currentStep($time);
        $previous = Totp::codeAt(self::RFC_SECRET, $step - 1);

        $this->assertSame($step - 1, Totp::verify(self::RFC_SECRET, $previous, null, $time));
        $this->assertNull(Totp::verify(self::RFC_SECRET, Totp::codeAt(self::RFC_SECRET, $step - 2), null, $time));
        $this->assertNull(Totp::verify(self::RFC_SECRET, $previous, $step - 1, $time));
        $this->assertNull(Totp::verify(self::RFC_SECRET, 'abcdef', null, $time));
    }
}
