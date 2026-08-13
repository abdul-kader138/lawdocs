<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Client extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('client');
    }

    protected $fillable = [
        'name', 'email', 'phone', 'street', 'suburb', 'state', 'postcode',
        'dob', 'gender', 'notes', 'created_by',
    ];

    protected $casts = [
        'dob' => 'date',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Flat attribute bag consumed by the wizard's client -> answers prefill
     * (see Precedent::client_field_map) — one place defining exactly which
     * Client columns are eligible to be mapped, so the admin-facing field
     * mapping UI and the actual prefill logic can't drift apart.
     *
     * @return array<string, mixed>
     */
    public function toPrefillAttributes(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'street' => $this->street,
            'suburb' => $this->suburb,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'dob' => $this->dob?->toDateString(),
            'gender' => $this->gender,
        ];
    }
}
