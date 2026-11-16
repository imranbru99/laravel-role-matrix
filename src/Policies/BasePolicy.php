<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Policies;

use Illuminate\Database\Eloquent\Model;
use ImranDevBD\RoleMatrix\Traits\AuthorizesWithMatrix;

abstract class BasePolicy
{
    use AuthorizesWithMatrix;

    abstract protected function module(): string;

    public function viewAny(Model $user): bool
    {
        return $this->matrix($user, $this->module().'.view');
    }

    public function view(Model $user, Model $record): bool
    {
        return $this->matrix($user, $this->module().'.view', $record);
    }

    public function create(Model $user): bool
    {
        return $this->matrix($user, $this->module().'.create');
    }

    public function update(Model $user, Model $record): bool
    {
        return $this->matrix($user, $this->module().'.update', $record);
    }

    public function delete(Model $user, Model $record): bool
    {
        return $this->matrix($user, $this->module().'.delete', $record);
    }

    public function restore(Model $user, Model $record): bool
    {
        return $this->matrix($user, $this->module().'.update', $record);
    }

    public function forceDelete(Model $user, Model $record): bool
    {
        return $this->matrix($user, $this->module().'.delete', $record);
    }
}
