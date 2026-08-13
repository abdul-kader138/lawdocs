<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Document extends Model
{
    use LogsActivity;

    public const ROOT = '/';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('document');
    }

    protected $fillable = [
        'owner_id', 'created_by', 'path', 'name', 'parent_path',
        'type', 'disk', 'storage_path', 'mime_type', 'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isFolder(): bool
    {
        return $this->type === 'folder';
    }

    /** Parent path for a given virtual path, e.g. "/A/B/C" -> "/A/B", "/A" -> "/". */
    public static function parentPathOf(string $path): string
    {
        $trimmed = rtrim($path, '/');
        $slash   = strrpos($trimmed, '/');

        return $slash > 0 ? substr($trimmed, 0, $slash) : self::ROOT;
    }

    /** Leaf name for a given virtual path, e.g. "/A/B/C" -> "C". */
    public static function nameOf(string $path): string
    {
        $trimmed = rtrim($path, '/');
        $slash   = strrpos($trimmed, '/');

        return $slash === false ? $trimmed : substr($trimmed, $slash + 1);
    }

    public static function joinPath(string $parentPath, string $name): string
    {
        return rtrim($parentPath, '/') === '' ? "/{$name}" : rtrim($parentPath, '/')."/{$name}";
    }

    /**
     * Shape expected by the SVAR File Manager's IEntity: the "id" IS the virtual
     * path (the frontend derives name/parent by splitting it on "/"). Folders are
     * always marked lazy so children are fetched on demand via request-data.
     */
    public function toEntity(): array
    {
        $entity = [
            'id'   => $this->path,
            'type' => $this->type,
            'date' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->isFolder()) {
            $entity['lazy'] = true;
        } else {
            $entity['size'] = $this->size;
        }

        return $entity;
    }
}
