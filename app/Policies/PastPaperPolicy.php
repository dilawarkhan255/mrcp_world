<?php

namespace App\Policies;

use App\Models\PastPaper;
use App\Models\PracticeSession;
use App\Models\QuestionImport;
use App\Models\User;

class PastPaperPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.papers.manage');
    }

    public function view(User $user, PastPaper $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PastPaper $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, PastPaper $record): bool
    {
        return $this->viewAny($user) && ! $record->questions()->withTrashed()->exists() && ! QuestionImport::where('past_paper_id', $record->id)->exists() && ! PracticeSession::where('past_paper_id', $record->id)->exists();
    }
}
