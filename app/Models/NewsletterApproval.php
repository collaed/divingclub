<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterApproval extends Model
{
    protected $fillable = ['newsletter_id', 'user_id', 'approved', 'comment'];

    protected function casts(): array
    {
        return ['approved' => 'boolean'];
    }

    public function newsletter()
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
