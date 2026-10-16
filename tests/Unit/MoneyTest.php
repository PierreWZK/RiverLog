<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_format_des_montants(): void
    {
        $this->assertSame("+342,50\u{00A0}€", Money::format('342.5'));
        $this->assertSame("\u{2212}1\u{00A0}250,00\u{00A0}€", Money::format(-1250));
        $this->assertSame("0,00\u{00A0}€", Money::format('0.00'));
        $this->assertSame("12,00\u{00A0}€", Money::format(12, signed: false));
    }

    public function test_taux_horaire(): void
    {
        $this->assertSame("+6,46\u{00A0}€/h", Money::hourly(6.46));
        $this->assertSame('—', Money::hourly(null));
    }

    public function test_durees(): void
    {
        $this->assertSame("2\u{00A0}h\u{00A0}45", Money::duration(165));
        $this->assertSame("1\u{00A0}h", Money::duration(60));
        $this->assertSame("45\u{00A0}min", Money::duration(45));
        $this->assertSame("1\u{00A0}h\u{00A0}05", Money::duration(65));
    }
}
