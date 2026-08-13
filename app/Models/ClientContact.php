<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class ClientContact extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('client_contact');
    }

    protected $fillable = [
        'client_id', 'name', 'relationship', 'email', 'phone',
        'street', 'suburb', 'state', 'postcode', 'dob', 'gender', 'notes',
    ];

    protected $casts = [
        'dob' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Flat attribute bag consumed when importing a contact into a party
     * group Repeater (see PartyGroupFormBuilder) — matched against the
     * group's own declared field names by direct key match (e.g. a
     * "guardians" group declaring a "relationship" field gets this
     * contact's relationship for free; a "beneficiaries" group with no
     * such field just doesn't pick it up). No config needed on either side
     * because this app already keeps party-group field names consistent
     * (name/relationship/gender/email/phone/address/dob) across precedents.
     *
     * @return array<string, mixed>
     */
    public function toImportableAttributes(): array
    {
        return [
            'name' => $this->name,
            'relationship' => $this->relationship,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->fullAddress(),
            'street' => $this->street,
            'suburb' => $this->suburb,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'dob' => $this->dob?->toDateString(),
            'gender' => $this->gender,
        ];
    }

    private function fullAddress(): ?string
    {
        $parts = array_filter([$this->street, $this->suburb, $this->state, $this->postcode]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
