<?php

namespace App\Models;

use Database\Factories\ContactMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

#[Fillable(['title', 'value', 'description', 'icon', 'sort_order'])]
class ContactMethod extends Model
{
    /** @use HasFactory<ContactMethodFactory> */
    use HasFactory;
}
