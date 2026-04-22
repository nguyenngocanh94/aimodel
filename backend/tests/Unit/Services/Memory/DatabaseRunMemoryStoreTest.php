<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Memory;

use App\Models\RunMemoryEntry;
use App\Services\Memory\DatabaseRunMemoryStore;
use App\Services\Memory\RunMemoryStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DatabaseRunMemoryStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function get_returns_null_when_missing(): void
    {
        $store = new DatabaseRunMemoryStore();

        $this->assertNull($store->get('workflow:demo', 'nope'));
    }

    #[Test]
    public function put_then_get_round_trips_value(): void
    {
        $store = new DatabaseRunMemoryStore();

        $store->put('workflow:demo', 'greeting', ['text' => 'hello']);

        $this->assertSame(['text' => 'hello'], $store->get('workflow:demo', 'greeting'));
    }

    #[Test]
    public function put_is_upsert_on_scope_and_key(): void
    {
        $store = new DatabaseRunMemoryStore();

        $store->put('s', 'k', ['v' => 1]);
        $store->put('s', 'k', ['v' => 2], meta: ['src' => 'x']);

        $this->assertSame(['v' => 2], $store->get('s', 'k'));
        $this->assertSame(1, RunMemoryEntry::query()->where('scope', 's')->where('key', 'k')->count());
    }

    #[Test]
    public function get_ignores_expired_entries(): void
    {
        $store = new DatabaseRunMemoryStore();

        $store->put('s', 'k', ['v' => 1], expiresAt: now()->subMinute());

        $this->assertNull($store->get('s', 'k'));
    }

    #[Test]
    public function forget_removes_row(): void
    {
        $store = new DatabaseRunMemoryStore();

        $store->put('s', 'k', ['v' => 1]);
        $store->forget('s', 'k');

        $this->assertNull($store->get('s', 'k'));
        $this->assertSame(0, RunMemoryEntry::query()->where('scope', 's')->where('key', 'k')->count());
    }

    #[Test]
    public function list_returns_only_active_entries_in_scope(): void
    {
        $store = new DatabaseRunMemoryStore();

        $store->put('s', 'a', ['v' => 1]);
        $store->put('s', 'b', ['v' => 2]);
        $store->put('s', 'c', ['v' => 3], expiresAt: now()->subMinute());
        $store->put('other', 'a', ['v' => 99]);

        $list = $store->list('s');

        $this->assertSame(['a' => ['v' => 1], 'b' => ['v' => 2]], $list);
    }

    #[Test]
    public function store_is_bound_in_container(): void
    {
        $this->assertInstanceOf(DatabaseRunMemoryStore::class, app(RunMemoryStore::class));
    }
}
