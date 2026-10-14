<?php

namespace Tests\Unit;

use App\Support\Csv;
use App\Support\Format;
use App\Support\Input;
use PHPUnit\Framework\TestCase;

class SupportTest extends TestCase
{
    public function test_money_input_normalization(): void
    {
        $this->assertSame('1500000', Input::money('1.500.000'));
        $this->assertSame('350000', Input::money('350,000 đ'));
        $this->assertSame('350000', Input::money(' 350 000 '));
        $this->assertSame('-1000', Input::money('-1.000'));
        $this->assertNull(Input::money(''));
        $this->assertSame('abc', Input::money('abc'));
        $this->assertSame('12,5', Input::money('12,5'));
        $this->assertSame('1.50.000', Input::money('1.50.000'));
    }

    public function test_decimal_input_normalization(): void
    {
        $this->assertSame('2.5', Input::decimal('2,5'));
        $this->assertSame('2.5', Input::decimal('2.5'));
        $this->assertNull(Input::decimal(' '));
    }

    public function test_csv_sanitize_blocks_formulas(): void
    {
        $this->assertSame("'=1+1", Csv::sanitize('=1+1'));
        $this->assertSame("'+84", Csv::sanitize('+84'));
        $this->assertSame("'-x", Csv::sanitize('-x'));
        $this->assertSame("'@cmd", Csv::sanitize('@cmd'));
        $this->assertSame("'\tx", Csv::sanitize("\tx"));
        $this->assertSame('Bình thường', Csv::sanitize('Bình thường'));
        $this->assertSame(-10, Csv::sanitize(-10));
        $this->assertSame('', Csv::sanitize(null));
    }

    public function test_formatting(): void
    {
        $this->assertSame('1.550.000 đ', Format::vnd(1_550_000));
        $this->assertSame('0 đ', Format::vnd(0));
        $this->assertSame('Chưa nhập', Format::vnd(null));
        $this->assertSame('2,5', Format::quantity('2.500'));
        $this->assertSame('12', Format::quantity('12.000'));
        $this->assertSame('2.5', Format::quantityInput('2.500'));
    }
}
