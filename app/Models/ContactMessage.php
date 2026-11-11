<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message sent from the public Contact page; read in the admin panel (Community → Contact messages). */
class ContactMessage extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_REPLIED = 'replied';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'phone', 'message', 'status', 'ip'];

    /** @var array<string, string> A new message starts as "new" (also before it is read back from the database). */
    protected $attributes = ['status' => self::STATUS_NEW];
}
