<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Database;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Attributes\ObservesModel;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Observer;
use YasserElgammal\Green\Database\ObserverRegistry;

// ─── Test doubles ────────────────────────────────────────────────────────────

class StubModel extends Model
{
    protected string $table = 'stubs';
    protected string $primaryKey = 'id';
}

class AnotherStubModel extends Model
{
    protected string $table = 'others';
    protected string $primaryKey = 'id';
}

class RecordingObserver extends Observer
{
    public array $events = [];

    public function creating(Model $model): bool
    {
        $this->events[] = 'creating';
        return true;
    }

    public function created(Model $model): void
    {
        $this->events[] = 'created';
    }

    public function updating(Model $model): bool
    {
        $this->events[] = 'updating';
        return true;
    }

    public function updated(Model $model): void
    {
        $this->events[] = 'updated';
    }

    public function deleting(Model $model): bool
    {
        $this->events[] = 'deleting';
        return true;
    }

    public function deleted(Model $model): void
    {
        $this->events[] = 'deleted';
    }

    public function saving(Model $model): bool
    {
        $this->events[] = 'saving';
        return true;
    }

    public function saved(Model $model): void
    {
        $this->events[] = 'saved';
    }
}

class HaltingCreatingObserver extends Observer
{
    public function creating(Model $model): bool
    {
        return false;
    }
}

class HaltingSavingObserver extends Observer
{
    public function saving(Model $model): bool
    {
        return false;
    }
}

class HaltingDeletingObserver extends Observer
{
    public function deleting(Model $model): bool
    {
        return false;
    }
}

class HaltingUpdatingObserver extends Observer
{
    public function updating(Model $model): bool
    {
        return false;
    }
}

#[ObservesModel(StubModel::class)]
class AttributeObserver extends Observer
{
    public array $events = [];

    public function created(Model $model): void
    {
        $this->events[] = 'created';
    }
}

#[ObservesModel(StubModel::class)]
#[ObservesModel(AnotherStubModel::class)]
class MultiModelAttributeObserver extends Observer
{
    public array $events = [];

    public function created(Model $model): void
    {
        $this->events[] = 'created:' . $model::class;
    }
}

class NoAttributeObserver extends Observer
{
}

// ─── Tests ───────────────────────────────────────────────────────────────────

class ObserverRegistryTest extends TestCase
{
    private ObserverRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ObserverRegistry();
    }

    // ── Explicit registration ────────────────────────────────────────────────

    public function test_register_explicit_observer_instance(): void
    {
        $observer = new RecordingObserver();
        $this->registry->register(StubModel::class, $observer);

        $observers = $this->registry->getObservers(StubModel::class);
        $this->assertCount(1, $observers);
        $this->assertSame($observer, $observers[0]);
    }

    public function test_register_explicit_observer_class_string(): void
    {
        $this->registry->register(StubModel::class, RecordingObserver::class);

        $observers = $this->registry->getObservers(StubModel::class);
        $this->assertCount(1, $observers);
        $this->assertInstanceOf(RecordingObserver::class, $observers[0]);
    }

    public function test_multiple_observers_on_same_model(): void
    {
        $this->registry->register(StubModel::class, new RecordingObserver());
        $this->registry->register(StubModel::class, new RecordingObserver());

        $this->assertCount(2, $this->registry->getObservers(StubModel::class));
    }

    public function test_observers_for_different_models_are_isolated(): void
    {
        $this->registry->register(StubModel::class, new RecordingObserver());

        $this->assertCount(1, $this->registry->getObservers(StubModel::class));
        $this->assertCount(0, $this->registry->getObservers(AnotherStubModel::class));
    }

    // ── Attribute-based registration ─────────────────────────────────────────

    public function test_register_from_attribute(): void
    {
        $observer = new AttributeObserver();
        $models = $this->registry->registerFromAttribute($observer);

        $this->assertSame([StubModel::class], $models);
        $this->assertCount(1, $this->registry->getObservers(StubModel::class));
        $this->assertSame($observer, $this->registry->getObservers(StubModel::class)[0]);
    }

    public function test_register_from_attribute_with_class_string(): void
    {
        $models = $this->registry->registerFromAttribute(AttributeObserver::class);

        $this->assertSame([StubModel::class], $models);
        $this->assertCount(1, $this->registry->getObservers(StubModel::class));
    }

    public function test_register_from_repeatable_attribute_binds_multiple_models(): void
    {
        $observer = new MultiModelAttributeObserver();
        $models = $this->registry->registerFromAttribute($observer);

        $this->assertCount(2, $models);
        $this->assertContains(StubModel::class, $models);
        $this->assertContains(AnotherStubModel::class, $models);

        $this->assertSame($observer, $this->registry->getObservers(StubModel::class)[0]);
        $this->assertSame($observer, $this->registry->getObservers(AnotherStubModel::class)[0]);
    }

    public function test_register_from_attribute_throws_without_attribute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no #[ObservesModel] attribute');

        $this->registry->registerFromAttribute(NoAttributeObserver::class);
    }

    // ── has / flush ──────────────────────────────────────────────────────────

    public function test_has_observers(): void
    {
        $this->assertFalse($this->registry->hasObservers(StubModel::class));

        $this->registry->register(StubModel::class, new RecordingObserver());

        $this->assertTrue($this->registry->hasObservers(StubModel::class));
    }

    public function test_flush_removes_observers_for_model(): void
    {
        $this->registry->register(StubModel::class, new RecordingObserver());
        $this->registry->register(AnotherStubModel::class, new RecordingObserver());

        $this->registry->flush(StubModel::class);

        $this->assertFalse($this->registry->hasObservers(StubModel::class));
        $this->assertTrue($this->registry->hasObservers(AnotherStubModel::class));
    }

    public function test_flush_all(): void
    {
        $this->registry->register(StubModel::class, new RecordingObserver());
        $this->registry->register(AnotherStubModel::class, new RecordingObserver());

        $this->registry->flushAll();

        $this->assertFalse($this->registry->hasObservers(StubModel::class));
        $this->assertFalse($this->registry->hasObservers(AnotherStubModel::class));
    }

    // ── Container resolution ─────────────────────────────────────────────────

    public function test_resolver_is_used_for_class_strings(): void
    {
        $expected = new RecordingObserver();
        $registry = new ObserverRegistry(
            fn (string $class) => $expected,
        );

        $registry->register(StubModel::class, RecordingObserver::class);

        $this->assertSame($expected, $registry->getObservers(StubModel::class)[0]);
    }

    public function test_resolver_rejects_non_observer_instance(): void
    {
        $registry = new ObserverRegistry(
            fn (string $class) => new \stdClass(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be an instance of');

        $registry->register(StubModel::class, RecordingObserver::class);
    }
}

class ObserverBaseClassTest extends TestCase
{
    public function test_all_before_hooks_return_true_by_default(): void
    {
        $observer = new class extends Observer {};
        $model = new StubModel(['name' => 'test']);

        $this->assertTrue($observer->creating($model));
        $this->assertTrue($observer->updating($model));
        $this->assertTrue($observer->deleting($model));
        $this->assertTrue($observer->saving($model));
    }

    public function test_after_hooks_are_callable_without_error(): void
    {
        $observer = new class extends Observer {};
        $model = new StubModel(['name' => 'test']);

        // After hooks are void — they should simply not throw
        $observer->created($model);
        $observer->updated($model);
        $observer->deleted($model);
        $observer->saved($model);

        $this->assertTrue(true); // No exception = pass
    }
}

class ObservesModelAttributeTest extends TestCase
{
    public function test_attribute_stores_model_class(): void
    {
        $reflection = new \ReflectionClass(AttributeObserver::class);
        $attrs = $reflection->getAttributes(ObservesModel::class);

        $this->assertCount(1, $attrs);
        $this->assertSame(StubModel::class, $attrs[0]->newInstance()->model);
    }

    public function test_repeatable_attribute_stores_multiple_model_classes(): void
    {
        $reflection = new \ReflectionClass(MultiModelAttributeObserver::class);
        $attrs = $reflection->getAttributes(ObservesModel::class);

        $this->assertCount(2, $attrs);

        $models = array_map(fn ($a) => $a->newInstance()->model, $attrs);
        $this->assertContains(StubModel::class, $models);
        $this->assertContains(AnotherStubModel::class, $models);
    }
}
