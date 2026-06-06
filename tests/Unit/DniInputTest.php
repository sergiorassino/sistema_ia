<?php

namespace Tests\Unit;

use App\Support\DniInput;
use PHPUnit\Framework\TestCase;

class DniInputTest extends TestCase
{
    public function test_strips_thousand_separators_spaces_and_non_digits(): void
    {
        $this->assertSame('25038868', DniInput::digitsOnly('25.038.868 '));
    }

    public function test_limits_to_eleven_digits(): void
    {
        $this->assertSame('12345678901', DniInput::digitsOnly('123456789012345'));
    }

    public function test_empty_and_nullish_input(): void
    {
        $this->assertSame('', DniInput::digitsOnly(''));
        $this->assertSame('', DniInput::digitsOnly(null));
        $this->assertSame('', DniInput::digitsOnly('   . - '));
    }
}
