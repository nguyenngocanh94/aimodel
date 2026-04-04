<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\DataType;
use App\Domain\PortDefinition;
use App\Domain\PortSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PortSchemaTest extends TestCase
{
    #[Test]
    public function can_create_empty_schema(): void
    {
        $schema = new PortSchema();

        $this->assertSame([], $schema->inputs);
        $this->assertSame([], $schema->outputs);
    }

    #[Test]
    public function can_create_schema_with_ports(): void
    {
        $input = PortDefinition::input('text', 'Text', DataType::Text);
        $output = PortDefinition::output('result', 'Result', DataType::Text);

        $schema = new PortSchema(
            inputs: [$input],
            outputs: [$output],
        );

        $this->assertCount(1, $schema->inputs);
        $this->assertCount(1, $schema->outputs);
        $this->assertSame($input, $schema->inputs[0]);
        $this->assertSame($output, $schema->outputs[0]);
    }

    #[Test]
    public function can_get_input_by_key(): void
    {
        $input1 = PortDefinition::input('text', 'Text', DataType::Text);
        $input2 = PortDefinition::input('prompt', 'Prompt', DataType::Prompt);

        $schema = new PortSchema(inputs: [$input1, $input2]);

        $this->assertSame($input1, $schema->getInput('text'));
        $this->assertSame($input2, $schema->getInput('prompt'));
        $this->assertNull($schema->getInput('nonexistent'));
    }

    #[Test]
    public function can_get_output_by_key(): void
    {
        $output1 = PortDefinition::output('result1', 'Result 1', DataType::Text);
        $output2 = PortDefinition::output('result2', 'Result 2', DataType::Json);

        $schema = new PortSchema(outputs: [$output1, $output2]);

        $this->assertSame($output1, $schema->getOutput('result1'));
        $this->assertSame($output2, $schema->getOutput('result2'));
        $this->assertNull($schema->getOutput('nonexistent'));
    }

    #[Test]
    public function has_input_checks_existence(): void
    {
        $input = PortDefinition::input('text', 'Text', DataType::Text);
        $schema = new PortSchema(inputs: [$input]);

        $this->assertTrue($schema->hasInput('text'));
        $this->assertFalse($schema->hasInput('nonexistent'));
    }

    #[Test]
    public function has_output_checks_existence(): void
    {
        $output = PortDefinition::output('result', 'Result', DataType::Text);
        $schema = new PortSchema(outputs: [$output]);

        $this->assertTrue($schema->hasOutput('result'));
        $this->assertFalse($schema->hasOutput('nonexistent'));
    }

    #[Test]
    public function throws_on_invalid_input_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All inputs must be PortDefinition instances');

        new PortSchema(inputs: ['not a port definition']);
    }

    #[Test]
    public function throws_on_invalid_output_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('All outputs must be PortDefinition instances');

        new PortSchema(outputs: ['not a port definition']);
    }

    #[Test]
    public function can_round_trip_through_array(): void
    {
        $input = PortDefinition::input('text', 'Text', DataType::Text, required: true);
        $output = PortDefinition::output('result', 'Result', DataType::Json, multiple: true);

        $original = new PortSchema(inputs: [$input], outputs: [$output]);
        $array = $original->toArray();
        $restored = PortSchema::fromArray($array);

        $this->assertCount(1, $restored->inputs);
        $this->assertCount(1, $restored->outputs);
        $this->assertSame($input->key, $restored->inputs[0]->key);
        $this->assertSame($output->key, $restored->outputs[0]->key);
        $this->assertSame($input->dataType, $restored->inputs[0]->dataType);
        $this->assertSame($output->dataType, $restored->outputs[0]->dataType);
    }
}
