<?php

namespace App\Models;

use Database\Factories\PunchCorrectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['punch_id', 'user_id', 'author_id', 'punch_date', 'action', 'before', 'after', 'reason'])]
class PunchCorrection extends Model
{
    /** @use HasFactory<PunchCorrectionFactory> */
    use HasFactory;

    public const string ACTION_ADDED = 'added';

    public const string ACTION_UPDATED = 'updated';

    public const string ACTION_DELETED = 'deleted';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'punch_date' => 'immutable_date',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_ADDED => 'Ajout',
            self::ACTION_UPDATED => 'Modification',
            self::ACTION_DELETED => 'Suppression',
            default => $this->action,
        };
    }

    public function summary(): string
    {
        $before = $this->before;
        $after = $this->after;

        return match ($this->action) {
            self::ACTION_ADDED => "{$after['label']} à {$after['time']}",
            self::ACTION_DELETED => "{$before['label']} de {$before['time']}",
            default => "{$before['label']} {$before['time']} → {$after['label']} {$after['time']}",
        };
    }

    /**
     * @return BelongsTo<Punch, $this>
     */
    public function punch(): BelongsTo
    {
        return $this->belongsTo(Punch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
