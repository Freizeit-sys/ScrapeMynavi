<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MynaviJobs extends Model
{
    use HasFactory;

		// 解決法: セキュリティの問題で複数の値を書き込めない　（Mass assignment）
		protected $guarded = [];
}
