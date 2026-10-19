<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Resources\SaveRecord;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Tables\Table;

/**
 * Admin screens for an Eloquent model, described in PHP: a list with
 * search, filters, sorting, actions and CSV export, and create/edit forms.
 * Register it with `$panel->resources(ProductResource::class)`.
 *
 *     final class ProductResource extends Resource
 *     {
 *         protected static string $model = Product::class;
 *
 *         public static function form(Form $form): Form
 *         {
 *             return $form->schema([
 *                 TextInput::make('name')->required(),
 *                 NumberInput::make('price')->min(0)->required(),
 *             ]);
 *         }
 *
 *         public static function table(Table $table): Table
 *         {
 *             return $table->columns([
 *                 Column::make('name')->searchable()->sortable(),
 *                 Column::make('price')->sortable(),
 *             ]);
 *         }
 *     }
 *
 * Who may do what: the model's policy (viewAny, view, create, update,
 * delete) when it has one, otherwise the permissions the panel registers
 * for the resource ("shop.products.view", ".create", ".update", ".delete").
 * `can()` may refuse more.
 *
 * @api
 */
abstract class Resource
{
    /** @var class-string<Model> */
    protected static string $model;

    /** @return class-string<Model> */
    public static function model(): string
    {
        return static::$model;
    }

    /** The URL segment and key: "products" for Product. */
    public static function key(): string
    {
        return Str::plural(Str::kebab(class_basename(static::model())));
    }

    public static function label(): string
    {
        return Str::headline(Str::plural(class_basename(static::model())));
    }

    public static function singularLabel(): string
    {
        return Str::headline(class_basename(static::model()));
    }

    public static function navigationGroup(): ?string
    {
        return null;
    }

    /** A lucide icon name (see the panel's navigation icons). */
    public static function navigationIcon(): string
    {
        return 'database';
    }

    public static function navigationSort(): int
    {
        return 100;
    }

    abstract public static function form(Form $form): Form;

    abstract public static function table(Table $table): Table;

    /**
     * The records the resource shows and edits. Narrow it to limit what
     * anyone can reach: records outside it are "not found".
     *
     * @return Builder<Model>
     */
    public static function query(): Builder
    {
        return static::model()::query();
    }

    /**
     * Saves a record from form values as the admin's form does: validated
     * by the form, only its fields written, and the activity log told. For
     * imports and tools; check the user may do it first.
     *
     *     TagResource::saveRecord(['title' => 'News']);          // creates
     *     TagResource::saveRecord(['title' => 'Updates'], $tag);  // edits
     *
     * @param  array<mixed>  $input
     *
     * @throws ValidationException
     * @throws PanelMisconfigured when the resource isn't registered
     */
    public static function saveRecord(array $input, ?Model $record = null): Model
    {
        $resource = null;
        foreach (app(Panel::class)->getResources() as $registered) {
            if ($registered->class === static::class) {
                $resource = $registered;
            }
        }
        if ($resource === null) {
            throw new PanelMisconfigured('The resource '.static::class.' isn\'t registered on the panel.');
        }
        $record ??= static::newRecord();
        $creating = ! $record->exists;
        $changed = app(SaveRecord::class)->handle($resource, $record, $input, $creating ? 'create' : 'edit');
        $creating
            ? app(ActivityLog::class)->record('created', $resource->key(), $record, static::title($record))
            : app(ActivityLog::class)->updated($resource->key(), $record, static::title($record), $changed);

        return $record;
    }

    /**
     * Facts shown beside the edit form (when, who, how much):
     * [['label' => 'Last login', 'value' => '2 hours ago'], …]. Keep it cheap.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function aside(Model $record): array
    {
        return [];
    }

    /**
     * Deletes a record, from the list, its form or a bulk delete, inside a
     * transaction. Override it to do more (hand something over, clean up).
     */
    public static function deleteRecord(Model $record, Authorizable $by): void
    {
        $record->delete();
    }

    /** The new record the create form fills. */
    public static function newRecord(): Model
    {
        $model = static::model();

        return new $model;
    }

    /**
     * A further check after the policy or permissions allowed `$ability`
     * ("view", "create", "update", "delete" or an action's ability): it can
     * only refuse. `$record` is null for the list, the create form and
     * whether to offer bulk actions (each selected record is checked too).
     */
    public static function can(Authorizable $user, string $ability, ?Model $record = null): bool
    {
        return true;
    }

    /** A record's name in headings and messages. */
    public static function title(Model $record): string
    {
        foreach (['name', 'title', 'label', 'email'] as $attribute) {
            $value = $record->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $key = $record->getKey();

        return static::singularLabel().' #'.(is_scalar($key) ? (string) $key : '');
    }
}
