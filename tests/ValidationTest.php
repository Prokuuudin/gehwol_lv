<?php
// tests/ValidationTest.php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../php/includes/validation.php';

final class ValidationTest extends TestCase
{
    public function test_required_field_errors_flags_missing_and_blank_fields(): void
    {
        $errors = required_field_errors(['name' => '', 'other' => 'x'], ['name', 'missing']);
        $this->assertCount(2, $errors);
    }

    public function test_required_field_errors_passes_when_all_present(): void
    {
        $errors = required_field_errors(['name' => 'GEHWOL'], ['name']);
        $this->assertSame([], $errors);
    }

    public function test_max_length_errors_flags_too_long_value(): void
    {
        $errors = max_length_errors(['name' => str_repeat('a', 300)], ['name' => 255]);
        $this->assertCount(1, $errors);
    }

    public function test_max_length_errors_passes_at_exact_limit(): void
    {
        $errors = max_length_errors(['name' => str_repeat('a', 255)], ['name' => 255]);
        $this->assertSame([], $errors);
    }
}
