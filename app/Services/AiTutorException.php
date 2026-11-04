<?php

namespace App\Services;

use RuntimeException;

/** A failure of the AI tutor with a message that is safe to show the student. */
class AiTutorException extends RuntimeException {}
