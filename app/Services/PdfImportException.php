<?php

namespace App\Services;

use RuntimeException;

/** A PDF upload that cannot be read or saved; the message is shown to the admin. */
class PdfImportException extends RuntimeException {}
