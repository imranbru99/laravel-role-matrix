<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use Illuminate\Database\Eloquent\Model;

trait UsesConfiguredKey
{
    public function initializeUsesConfiguredKey(): void
    {
        if (RoleMatrixConfig::keyType() === \ImranDevBD\RoleMatrix\Enums\KeyType::Int) {
            return;
        }

        $this->keyType = 'string';
        $this->incrementing = false;
    }

    public static function bootUsesConfiguredKey(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getIncrementing()) {
                return;
            }

            $key = $model->getKeyName();

            if ($model->getAttribute($key) === null || $model->getAttribute($key) === '') {
                $model->setAttribute($key, SchemaKey::newKey());
            }
        });
    }
}
