<?php

namespace Tests\Unit;

use App\Services\CameraTotp;
use PHPUnit\Framework\TestCase;

class CameraTotpTest extends TestCase
{
    public function test_rfc_6238_sha1_reference_vectors(): void
    {
        $totp = new CameraTotp;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471',
            1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $time => $expected) {
            $this->assertSame($expected, $totp->code($secret, intdiv($time, 30), 8));
        }
    }
}
