<?php

namespace App\Policies;

use App\Models\AtlasImage;
use App\Models\User;

class AtlasImagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.atlas.manage');
    }

    public function view(User $user, AtlasImage $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AtlasImage $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, AtlasImage $record): bool
    {
        return $this->viewAny($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }
}
