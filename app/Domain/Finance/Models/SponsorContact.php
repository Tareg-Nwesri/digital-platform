<?php

namespace App\Domain\Finance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $sponsor_id
 * @property string $name
 * @property string|null $role
 * @property string|null $phone
 * @property string|null $email
 */
#[Fillable(['sponsor_id', 'name', 'role', 'phone', 'email'])]
class SponsorContact extends Model {}
