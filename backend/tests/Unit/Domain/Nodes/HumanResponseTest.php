<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Nodes;

use App\Domain\Nodes\HumanResponse;
use Tests\TestCase;

class HumanResponseTest extends TestCase
{
    public function test_pick_factory_creates_correct_type_with_selected_index(): void
    {
        $response = HumanResponse::pick(0);

        $this->assertSame('pick', $response->type);
        $this->assertSame(0, $response->selectedIndex);
        $this->assertNull($response->editedContent);
        $this->assertNull($response->feedback);
    }

    public function test_edit_factory_creates_correct_type(): void
    {
        $response = HumanResponse::edit('revised text', 1);

        $this->assertSame('edit', $response->type);
        $this->assertSame(1, $response->selectedIndex);
        $this->assertSame('revised text', $response->editedContent);
        $this->assertNull($response->feedback);
    }

    public function test_prompt_back_factory_creates_correct_type(): void
    {
        $response = HumanResponse::promptBack('make it shorter');

        $this->assertSame('prompt_back', $response->type);
        $this->assertNull($response->selectedIndex);
        $this->assertNull($response->editedContent);
        $this->assertSame('make it shorter', $response->feedback);
    }

    public function test_is_pick_returns_correct_boolean(): void
    {
        $pick = HumanResponse::pick(0);
        $edit = HumanResponse::edit('text');
        $promptBack = HumanResponse::promptBack('feedback');

        $this->assertTrue($pick->isPick());
        $this->assertFalse($edit->isPick());
        $this->assertFalse($promptBack->isPick());
    }

    public function test_is_edit_returns_correct_boolean(): void
    {
        $pick = HumanResponse::pick(0);
        $edit = HumanResponse::edit('text');
        $promptBack = HumanResponse::promptBack('feedback');

        $this->assertFalse($pick->isEdit());
        $this->assertTrue($edit->isEdit());
        $this->assertFalse($promptBack->isEdit());
    }

    public function test_is_prompt_back_returns_correct_boolean(): void
    {
        $pick = HumanResponse::pick(0);
        $edit = HumanResponse::edit('text');
        $promptBack = HumanResponse::promptBack('feedback');

        $this->assertFalse($pick->isPromptBack());
        $this->assertFalse($edit->isPromptBack());
        $this->assertTrue($promptBack->isPromptBack());
    }

    public function test_invalid_type_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Response type must be pick, edit, or prompt_back, got: invalid');

        new HumanResponse(type: 'invalid');
    }

    public function test_to_array_excludes_null_fields(): void
    {
        $pick = HumanResponse::pick(2);
        $this->assertSame([
            'type' => 'pick',
            'selectedIndex' => 2,
        ], $pick->toArray());

        $edit = HumanResponse::edit('new text');
        $this->assertSame([
            'type' => 'edit',
            'editedContent' => 'new text',
        ], $edit->toArray());

        $promptBack = HumanResponse::promptBack('try again');
        $this->assertSame([
            'type' => 'prompt_back',
            'feedback' => 'try again',
        ], $promptBack->toArray());
    }
}
