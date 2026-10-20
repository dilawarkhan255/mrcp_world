<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\PastPaper;
use App\Models\PracticeSession;
use App\Models\QuestionImport;
use App\Models\User;

class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->hasAnyRole(['editor', 'admin']);
    }

    public function view(User $user, Exam $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Exam $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Exam $record): bool
    {
        return $this->viewAny($user) && ! $record->categories()->exists() && ! PastPaper::where('exam_id', $record->id)->exists() && ! QuestionImport::where('exam_id', $record->id)->exists() && ! PracticeSession::where('exam_id', $record->id)->exists();
    }
}
