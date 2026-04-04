<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\DataType;
use App\Domain\PortDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PortDefinitionTest extends TestCase
{
    #[Test]
    public function can_create_input_port(): void
    {
        $port = PortDefinition::input(
            key: 'script',
            label: 'Script',
            dataType: DataType::Script,
            required: true,
            multiple: false,
            description: 'The input script',
        );

        $this->assertSame('script', $port->key);
        $this->assertSame('Script', $port->label);
        $this->assertSame('input', $port->direction);
        $this->assertSame(DataType::Script, $port->dataType);
        $this->assertTrue($port->required);
        $this->assertFalse($port->multiple);
        $this->assertSame('The input script', $port->description);
        $this->assertTrue($port->isInput());
        $this->assertFalse($port->isOutput());
    }

    #[Test]
    public function can_create_output_port(): void
    {
        $port = PortDefinition::output(
            key: 'result',
            label: 'Result',
            dataType: DataType::Text,
            multiple: true,
        );

        $this->assertSame('result', $port->key);
        $this->assertSame('output', $port->direction);
        $this->assertSame(DataType::Text, $port->dataType);
        $this->assertFalse($port->required); // outputs are never required
        $this->assertTrue($port->multiple);
        $this->assertTrue($port->isOutput());
        $this->assertFalse($port->isInput());
    }

    #[Test]
    public function throws_on_invalid_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Direction must be 'input' or 'output'");

        new PortDefinition(
            key: 'test',
            label: 'Test',
            direction: 'invalid',
            dataType: DataType::Text,
            required: true,
            multiple: false,
        );
    }

    #[Test]
    public function can_round_trip_through_array(): void
    {
        $original = PortDefinition::input(
            key: 'prompt',
            label: 'Prompt',
            dataType: DataType::Prompt,
            required: true,
            multiple: false,
            description: 'Input prompt',
        );

        $array = $original->toArray();
        $restored = PortDefinition::fromArray($array);

        $this->assertSame($original->key, $restored->key);
        $this->assertSame($original->label, $restored->label);
        $this->assertSame($original->direction, $restored->direction);
        $this->assertSame($original->dataType, $restored->dataType);
        $this->assertSame($original->required, $restored->required);
        $this->assertSame($original->multiple, $restored->multiple);
        $this->assertSame($original->description, $restored->description);
    }
}
