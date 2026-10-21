<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.questions.view');
    }

    public function view(User $user, Question $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('content.questions.create');
    }

    public function update(User $user, Question $record): bool
    {
        return $user->can('content.questions.update');
    }

    /** Only an admin may publish a question to the students. */
    public function publish(User $user, Question $record): bool
    {
        return $user->can('content.questions.publish');
    }

    public function delete(User $user, Question $record): bool
    {
        return $user->can('content.questions.delete');
    }
}
