<?php

namespace App\Models;

use Eloquent;

class Lead extends Eloquent
{
    /**
     * The database connection used by this model.
     * Points to the ai_engine SQLite database (leads.db).
     *
     * @var string
     */
    protected $connection = 'sqlite_leads';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'leads';

    /**
     * The primary key is a string UUID-style id (no auto increment).
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Whether the model has created_at / updated_at timestamps.
     * The leads table stores created_at only (ISO string, no updated_at).
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'created_at', 'source', 'conversation_id', 'raw_message',
        'student_name', 'phone_number', 'branch_or_level', 'lead_score',
        'level', 'filiere', 'subject', 'ai_reply',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
    ];

    /**
     * Computed accessor: student_name (with fallback to legacy 'name' column).
     *
     * @return string|null
     */
    public function getDisplayNameAttribute(): ?string
    {
        return $this->student_name ?? $this->attributes['name'] ?? null;
    }

    /**
     * Computed accessor: phone_number (with fallback to legacy 'phone' column).
     *
     * @return string|null
     */
    public function getDisplayPhoneAttribute(): ?string
    {
        return $this->phone_number ?? $this->attributes['phone'] ?? null;
    }

    /**
     * A lead is considered "interested" (HOT) when lead_score is HOT.
     *
     * @return bool
     */
    public function getIsInterestedAttribute(): bool
    {
        return strtoupper($this->lead_score ?? '') === 'HOT';
    }
}
