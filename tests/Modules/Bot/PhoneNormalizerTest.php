<?php

namespace Tests\Modules\Bot;

use App\Modules\Bot\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    /**
     * @dataProvider normalizationProvider
     */
    public function testNormalize(?string $input, ?string $expected): void
    {
        self::assertSame($expected, PhoneNormalizer::normalize($input));
    }

    /**
     * @return array<string,array{0:?string,1:?string}>
     */
    public function normalizationProvider(): array
    {
        return [
            'canonical'            => ['09123456789', '09123456789'],
            'persian digits'       => ['۰۹۱۲۳۴۵۶۷۸۹', '09123456789'],
            'arabic digits'        => ['٠٩١٢٣٤٥٦٧٨٩', '09123456789'],
            'plus 98'              => ['+989123456789', '09123456789'],
            'plus 98 separated'    => ['+98 912 345 6789', '09123456789'],
            'double zero 98'       => ['00989123456789', '09123456789'],
            'bare 98'              => ['989123456789', '09123456789'],
            'without leading zero' => ['9123456789', '09123456789'],
            'with dashes'          => ['0912-345-6789', '09123456789'],
            'with parentheses'     => ['(0912) 345 6789', '09123456789'],
            'landline rejected'    => ['02112345678', null],
            'short rejected'       => ['091234567', null],
            'text rejected'        => ['not-a-phone', null],
            'empty string'         => ['', null],
            'null'                 => [null, null],
        ];
    }

    public function testCandidateFormsContainEquivalentRepresentations(): void
    {
        $forms = PhoneNormalizer::candidateForms('09123456789');

        self::assertContains('09123456789', $forms);
        self::assertContains('9123456789', $forms);
        self::assertContains('+989123456789', $forms);
        self::assertContains('00989123456789', $forms);
        self::assertContains('989123456789', $forms);
    }

    public function testIsCanonical(): void
    {
        self::assertTrue(PhoneNormalizer::isCanonical('09123456789'));
        self::assertFalse(PhoneNormalizer::isCanonical('9123456789'));
        self::assertFalse(PhoneNormalizer::isCanonical('0912345678'));
    }
}
